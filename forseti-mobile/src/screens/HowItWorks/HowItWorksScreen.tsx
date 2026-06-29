/**
 * How It Works Screen - Explains Forseti's technology
 */

import React from 'react';
import { View, Text, StyleSheet, ScrollView } from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { Colors } from '../../utils/colors';

const HowItWorksScreen: React.FC = () => {
  return (
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        <Icon name="cog" size={64} color={Colors.primary} />
        <Text style={styles.title}>How Forseti Works</Text>
      </View>

      <View style={styles.content}>
        <View style={styles.simpleAnswer}>
          <Text style={styles.simpleAnswerLabel}>Simple Answer:</Text>
          <Text style={styles.simpleAnswerText}>
            We use AI to analyze crime patterns and alert you when you enter areas with elevated
            safety concerns based on your geographic location and situational context.
          </Text>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>The Technology Behind Community Safety</Text>

          <View style={styles.step}>
            <View style={styles.stepNumber}>
              <Text style={styles.stepNumberText}>1</Text>
            </View>
            <View style={styles.stepContent}>
              <View style={styles.stepHeader}>
                <Icon name="database" size={24} color={Colors.primary} />
                <Text style={styles.stepTitle}>Data Collection 📊</Text>
              </View>
              <Text style={styles.stepDescription}>
                We continuously gather crime incident data from Philadelphia Police Department open
                data sources, emergency service reports, and community submissions. All data is
                verified and anonymized to protect privacy.
              </Text>
            </View>
          </View>

          <View style={styles.step}>
            <View style={styles.stepNumber}>
              <Text style={styles.stepNumberText}>2</Text>
            </View>
            <View style={styles.stepContent}>
              <View style={styles.stepHeader}>
                <Icon name="brain" size={24} color={Colors.primary} />
                <Text style={styles.stepTitle}>AI Pattern Recognition 🧠</Text>
              </View>
              <Text style={styles.stepDescription}>
                Our machine learning algorithms analyze historical and real-time data to identify:
                temporal patterns (when crimes are most likely to occur), spatial patterns
                (geographic clustering and crime migration), trend analysis (increasing or
                decreasing crime rates over time), and predictive modeling (forecasting high-risk
                areas and times).
              </Text>
            </View>
          </View>

          <View style={styles.step}>
            <View style={styles.stepNumber}>
              <Text style={styles.stepNumberText}>3</Text>
            </View>
            <View style={styles.stepContent}>
              <View style={styles.stepHeader}>
                <Icon name="hexagon-multiple" size={24} color={Colors.primary} />
                <Text style={styles.stepTitle}>H3 Geospatial Analysis 🗺️</Text>
              </View>
              <Text style={styles.stepDescription}>
                Using Uber's H3 hexagonal hierarchical geospatial indexing system, we map crime
                incidents with incredible precision. Unlike traditional square grids, H3 hexagons
                provide more accurate spatial analysis and better visualization of crime patterns.
                Resolution levels range from neighborhood overviews to block-level details, enabling
                incident density calculations for hotspot identification and neighbor analysis to
                understand how crime spreads between adjacent areas.
              </Text>
            </View>
          </View>

          <View style={styles.step}>
            <View style={styles.stepNumber}>
              <Text style={styles.stepNumberText}>4</Text>
            </View>
            <View style={styles.stepContent}>
              <View style={styles.stepHeader}>
                <Icon name="bell-alert" size={24} color={Colors.primary} />
                <Text style={styles.stepTitle}>Intelligent Alerts 🔔</Text>
              </View>
              <Text style={styles.stepDescription}>
                When our AI detects concerning patterns or emerging threats, we send targeted alerts
                to pedestrians passing through the area, residents in affected areas, neighborhood
                watch coordinators, community safety groups, and local authorities (with user
                consent).
              </Text>
            </View>
          </View>

          <View style={styles.step}>
            <View style={styles.stepNumber}>
              <Text style={styles.stepNumberText}>5</Text>
            </View>
            <View style={styles.stepContent}>
              <View style={styles.stepHeader}>
                <Icon name="shield-check" size={24} color={Colors.primary} />
                <Text style={styles.stepTitle}>Community Feedback Loop 🔄</Text>
              </View>
              <Text style={styles.stepDescription}>
                User reports and feedback help improve our AI models. When community members report
                incidents or validate our predictions, our system becomes smarter and more accurate.
              </Text>
            </View>
          </View>
        </View>

        <View style={styles.privacySection}>
          <Icon name="lock-check" size={32} color={Colors.primary} />
          <Text style={styles.privacySectionTitle}>Privacy First</Text>
          <Text style={styles.privacyText}>
            Your location data is encrypted and never shared with third parties. We use anonymous
            aggregate data to improve the service while protecting individual privacy.
          </Text>
        </View>

        <View style={styles.callout}>
          <Text style={styles.calloutTitle}>Want to Learn More?</Text>
          <Text style={styles.calloutText}>
            Chat with Forseti to ask specific questions about how the technology works, our data
            sources, or our commitment to privacy and security.
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
  privacySection: {
    alignItems: 'center',
    backgroundColor: Colors.white,
    borderRadius: 12,
    elevation: 3,
    marginBottom: 24,
    padding: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  privacySectionTitle: {
    color: Colors.primary,
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 12,
    marginTop: 12,
  },
  privacyText: {
    color: Colors.text,
    fontSize: 15,
    lineHeight: 22,
    textAlign: 'center',
  },
  section: {
    marginBottom: 32,
  },
  sectionTitle: {
    color: Colors.primary,
    fontSize: 22,
    fontWeight: 'bold',
    marginBottom: 24,
  },
  simpleAnswer: {
    backgroundColor: Colors.primaryLight,
    borderLeftColor: Colors.primary,
    borderLeftWidth: 4,
    borderRadius: 8,
    marginBottom: 24,
    padding: 16,
  },
  simpleAnswerLabel: {
    color: Colors.primary,
    fontSize: 16,
    fontWeight: 'bold',
    marginBottom: 8,
  },
  simpleAnswerText: {
    color: Colors.text,
    fontSize: 16,
    lineHeight: 24,
  },
  step: {
    flexDirection: 'row',
    marginBottom: 24,
  },
  stepContent: {
    flex: 1,
  },
  stepDescription: {
    color: Colors.text,
    fontSize: 15,
    lineHeight: 22,
  },
  stepHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    marginBottom: 8,
  },
  stepNumber: {
    alignItems: 'center',
    backgroundColor: Colors.primary,
    borderRadius: 20,
    height: 40,
    justifyContent: 'center',
    marginRight: 16,
    width: 40,
  },
  stepNumberText: {
    color: Colors.white,
    fontSize: 18,
    fontWeight: 'bold',
  },
  stepTitle: {
    color: Colors.dark,
    fontSize: 18,
    fontWeight: 'bold',
    marginLeft: 8,
  },
  title: {
    color: Colors.primary,
    fontSize: 28,
    fontWeight: 'bold',
    marginTop: 16,
  },
});

export default HowItWorksScreen;
