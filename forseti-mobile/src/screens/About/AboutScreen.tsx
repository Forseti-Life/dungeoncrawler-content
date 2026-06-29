/**
 * About Screen - Information about Forseti
 */

import React from 'react';
import { View, Text, StyleSheet, ScrollView } from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { Colors } from '../../utils/colors';

const AboutScreen: React.FC = () => {
  return (
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        <Icon name="shield-account" size={64} color={Colors.primary} />
        <Text style={styles.title}>About Forseti</Text>
        <Text style={styles.tagline}>AI Looking Out For Us</Text>
      </View>

      <View style={styles.content}>
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Our Mission</Text>
          <Text style={styles.paragraph}>
            We believe technology should serve humanity by protecting individuals and communities by
            improving quality of life for as many people as possible. Forseti is a super
            intelligence in its infancy with the mission to protect its community members.
          </Text>
          <Text style={styles.paragraph}>
            Named after the Norse god of justice and peaceful resolution, Forseti represents our
            commitment to fair, intelligent, and proactive safety measures. Our platform aims to
            resolve community safety challenges through technology, transparency, and collaboration.
          </Text>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Core Values</Text>

          <View style={styles.valueCard}>
            <View style={styles.valueHeader}>
              <Icon name="eye" size={24} color={Colors.primary} />
              <Text style={styles.valueTitle}>Vigilance</Text>
            </View>
            <Text style={styles.valueDescription}>
              24/7 AI monitoring ensures constant awareness of situational safety conditions across
              Philadelphia.
            </Text>
          </View>

          <View style={styles.valueCard}>
            <View style={styles.valueHeader}>
              <Icon name="magnify" size={24} color={Colors.primary} />
              <Text style={styles.valueTitle}>Transparency</Text>
            </View>
            <Text style={styles.valueDescription}>
              Open data and clear communication about safety trends and our methods.
            </Text>
          </View>

          <View style={styles.valueCard}>
            <View style={styles.valueHeader}>
              <Icon name="scale-balance" size={24} color={Colors.primary} />
              <Text style={styles.valueTitle}>Justice</Text>
            </View>
            <Text style={styles.valueDescription}>
              Fair and unbiased safety measures that protect all community members equally.
            </Text>
          </View>

          <View style={styles.valueCard}>
            <View style={styles.valueHeader}>
              <Icon name="account-group" size={24} color={Colors.primary} />
              <Text style={styles.valueTitle}>Community</Text>
            </View>
            <Text style={styles.valueDescription}>
              Empowering residents with knowledge and tools to take ownership of their safety.
            </Text>
          </View>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Philadelphia Focus</Text>
          <Text style={styles.paragraph}>
            We've chosen to focus our initial efforts on Philadelphia because we are based in
            Philadelphia. By deeply understanding one community's unique safety challenges, we can
            create more effective solutions. As we prove our model, we plan to expand to other
            cities facing similar challenges to protect our community members anywhere they go.
          </Text>
        </View>

        <View style={styles.callout}>
          <Text style={styles.calloutTitle}>Join Our Mission</Text>
          <Text style={styles.calloutText}>
            We're always looking for community members, safety advocates, and technology partners
            who share our vision. Use the Chat feature to talk with Forseti and learn how you can
            contribute to safer communities.
          </Text>
        </View>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  callout: {
    backgroundColor: Colors.primaryLight,
    borderRadius: 12,
    marginTop: 16,
    padding: 20,
  },
  calloutText: {
    color: Colors.text,
    fontSize: 16,
    lineHeight: 24,
  },
  calloutTitle: {
    color: Colors.primary,
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 12,
  },
  container: {
    backgroundColor: Colors.background,
    flex: 1,
  },
  content: {
    padding: 20,
  },
  header: {
    alignItems: 'center',
    backgroundColor: Colors.white,
    borderBottomColor: Colors.lightGray,
    borderBottomWidth: 1,
    paddingHorizontal: 20,
    paddingVertical: 32,
  },
  paragraph: {
    color: Colors.text,
    fontSize: 16,
    lineHeight: 24,
    marginBottom: 16,
  },
  section: {
    marginBottom: 32,
  },
  sectionTitle: {
    color: Colors.primary,
    fontSize: 22,
    fontWeight: 'bold',
    marginBottom: 16,
  },
  tagline: {
    color: Colors.gray,
    fontSize: 16,
    fontStyle: 'italic',
  },
  title: {
    color: Colors.primary,
    fontSize: 28,
    fontWeight: 'bold',
    marginBottom: 8,
    marginTop: 16,
  },
  valueCard: {
    backgroundColor: Colors.white,
    borderRadius: 12,
    elevation: 3,
    marginBottom: 12,
    padding: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  valueDescription: {
    color: Colors.text,
    fontSize: 14,
    lineHeight: 20,
  },
  valueHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    marginBottom: 8,
  },
  valueTitle: {
    color: Colors.dark,
    fontSize: 18,
    fontWeight: 'bold',
    marginLeft: 12,
  },
});

export default AboutScreen;
