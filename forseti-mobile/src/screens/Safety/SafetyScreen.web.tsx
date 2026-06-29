/**
 * Safety Factors Screen - Web Version
 *
 * Displays the 7 dimensions of safety from forseti.life/safety-factors
 *
 * TODO: Future Enhancement - Replace with API integration
 * TODO: Create Drupal endpoint: /api/safety-factors
 * TODO: Fetch content dynamically instead of hardcoding
 *
 * Content sourced from: https://forseti.life/safety-factors
 * Last updated: December 22, 2025
 */

import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Image } from 'react-native';
import Icon from '../../components/Icon.web';
import { Colors } from '../../utils/colors';

interface SafetyFactor {
  id: string;
  title: string;
  subtitle: string;
  description: string;
  icon: any;
  factors: string[];
  isActive: boolean;
  planned: boolean;
}

const SafetyScreen: React.FC = () => {
  const [expandedId, setExpandedId] = useState<string | null>('safe');

  const safetyFactors: SafetyFactor[] = [
    {
      id: 'safe',
      title: 'Safe (Security)',
      subtitle: 'The Foundation of Predictability',
      description:
        'The reliable absence of immediate threat. It represents a state where the nervous system can shift from defense (fight/flight) to maintenance (rest/digest). It is characterized by physical protection, financial stability, and a predictable environment where one can sleep without fear.',
      icon: require('../../../assets/images/forseti_safe.png'),
      isActive: true,
      planned: false,
      factors: [
        'Violent Crime: Assault, robbery, homicide, domestic violence',
        'Property Crime: Burglary, theft, vandalism, vehicle break-ins',
        'Emergency Response: Police, fire, ambulance accessibility (Planned)',
        'Building Security: Locks, alarms, surveillance (Planned)',
        'Police Presence: Regular patrols, station proximity (Planned)',
        'Crime Trends: Historical patterns, emerging threats (Planned)',
        'Environmental Quality: Clean air/water, pollution levels (Planned)',
        'Street Lighting: Adequate illumination in public spaces (Planned)',
        'Traffic Safety: Pedestrian infrastructure, bike lanes (Planned)',
        'Natural Hazards: Flood zones, weather preparedness (Planned)',
      ],
    },
    {
      id: 'energized',
      title: 'Energized (Vitality)',
      subtitle: 'The Biological Fuel',
      description:
        'The move beyond mere survival to physiological optimization. This level focuses on accumulating the resources required to live, not just exist. It encompasses housing stability, food security, and financial well-being—the fundamental resources that provide the surplus "fuel" needed for higher pursuits.',
      icon: require('../../../assets/images/forseti_energized.png'),
      isActive: false,
      planned: true,
      factors: [
        'Housing Stability: Affordable housing, habitability standards',
        'Food Security: Access to nutritious food, grocery stores',
        'Financial Well-being: Income stability, living wages, emergency savings',
        'Utility Access: Reliable electricity, heating, water, internet',
        'Transportation Access: Public transit, walkability, vehicle access',
        'Economic Opportunity: Employment availability, job training',
      ],
    },
    {
      id: 'connected',
      title: 'Connected (Community)',
      subtitle: 'The Alignment of Shared Values',
      description:
        'The establishment of a Tribe. This goes beyond simple social safety; it defines the deep satisfaction of being interconnected with people who share your specific interests, values, and mission. It is the move from "fitting in" to "belonging."',
      icon: require('../../../assets/images/forseti_connected.png'),
      isActive: false,
      planned: true,
      factors: [
        'Community Engagement: Neighborhood associations, block parties',
        'Social Cohesion: Trust among neighbors, mutual support networks',
        'Neighborhood Watch: Community surveillance, organized vigilance',
        'Public Spaces: Community centers, gathering places',
        'Green Spaces: Parks, recreation areas, urban forests',
        'Anti-Discrimination: Inclusive environment, hate crime monitoring',
        'Youth Programs: After-school activities, mentorship, recreation',
      ],
    },
    {
      id: 'free',
      title: 'Free (Autonomy)',
      subtitle: 'The Power of Self-Determination',
      description:
        "The liberation from coercion and the assertion of the self. This is the ability to set boundaries, make independent choices, and direct one's own path without being controlled by the expectations, debts, or demands of others.",
      icon: require('../../../assets/images/forseti_free.png'),
      isActive: false,
      planned: true,
      factors: [
        'Personal Freedom: Freedom from coercion, harassment, intimidation',
        'Privacy Rights: Data protection, surveillance awareness',
        'Legal Protections: Access to justice, tenant rights, worker protections',
        'Financial Independence: Freedom from predatory lending, debt traps',
        'Movement Freedom: Safe navigation of public spaces day and night',
        'Information Access: Reliable communication, internet access, education',
      ],
    },
    {
      id: 'capable',
      title: 'Capable (Mastery)',
      subtitle: 'The Development of Expertise',
      description:
        'The pursuit of excellence and competence in valued skills. This dimension focuses on the ability to develop expertise, gain recognition for your abilities, and continuously improve through deliberate practice and challenge.',
      icon: require('../../../assets/images/forseti_capable.png'),
      isActive: false,
      planned: true,
      factors: [
        'Educational Access: Quality schools, libraries, learning resources',
        'Skill Development: Training programs, apprenticeships, certification',
        'Professional Growth: Career advancement opportunities, mentorship',
        'Digital Literacy: Technology access, computer skills, online learning',
        'Health Literacy: Understanding health information, medical access',
        'Financial Literacy: Money management, investment knowledge',
      ],
    },
    {
      id: 'useful',
      title: 'Useful (Purpose)',
      subtitle: 'Contributing to Something Greater',
      description:
        "The drive to make meaningful contributions beyond oneself. This dimension captures the human need to feel that one's existence matters, that your work serves others, and that you're part of something larger than individual success.",
      icon: require('../../../assets/images/forseti_useful.png'),
      isActive: false,
      planned: true,
      factors: [
        'Volunteer Opportunities: Community service, civic engagement',
        'Civic Participation: Voting access, community boards, local government',
        'Meaningful Work: Employment that serves community needs',
        'Social Impact: Ability to create positive change in neighborhood',
        'Mentorship Roles: Opportunities to guide and support others',
        'Cultural Contribution: Arts, culture, heritage preservation',
      ],
    },
    {
      id: 'whole',
      title: 'Whole (Holistic Health)',
      subtitle: 'Integration of All Dimensions',
      description:
        'The synthesis of all safety dimensions into a cohesive whole. This represents the highest level where physical security, vitality, community, autonomy, mastery, and purpose work together to create complete well-being.',
      icon: require('../../../assets/images/forseti_whole.png'),
      isActive: false,
      planned: true,
      factors: [
        'Mental Health: Access to counseling, stress management, wellness',
        'Physical Health: Healthcare access, preventive care, fitness facilities',
        'Work-Life Balance: Adequate rest, recreation, family time',
        'Spiritual Well-being: Access to faith communities, meditation, nature',
        'Environmental Harmony: Connection to nature, sustainable living',
        'Life Satisfaction: Overall contentment, happiness, fulfillment',
      ],
    },
  ];

  const toggleExpanded = (id: string) => {
    setExpandedId(expandedId === id ? null : id);
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.contentContainer}>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.title}>Safety Factors</Text>
        <Text style={styles.subtitle}>
          Understanding safety through the lens of Maslow's Hierarchy of Needs
        </Text>
      </View>

      {/* Info Card */}
      <View style={styles.infoCard}>
        <Text style={styles.infoTitle}>📊 Comprehensive Safety Assessment</Text>
        <Text style={styles.infoText}>
          Forseti evaluates safety across multiple dimensions, recognizing that true security
          encompasses physical, social, and psychological well-being.
        </Text>
      </View>

      {/* Section Header */}
      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Seven Dimensions of Safety</Text>
        <Text style={styles.sectionDescription}>
          Our comprehensive safety framework recognizes that true security encompasses physical
          protection, vitality, community trust, personal freedom, capability, purpose, and holistic
          well-being. This is our framework and our roadmap for priority.
        </Text>
      </View>

      {/* Accordion Items */}
      {safetyFactors.map(factor => {
        const isExpanded = expandedId === factor.id;
        return (
          <View key={factor.id} style={styles.accordionItem}>
            <TouchableOpacity
              style={styles.accordionHeader}
              onPress={() => toggleExpanded(factor.id)}
            >
              <View style={styles.accordionHeaderContent}>
                <Image source={factor.icon} style={styles.factorIcon} resizeMode="contain" />
                <View style={styles.headerTextContainer}>
                  <Text style={styles.accordionTitle}>
                    {factor.title}
                    {factor.planned && (
                      <Text style={styles.plannedBadge}> (Planned Enhancement)</Text>
                    )}
                  </Text>
                </View>
              </View>
              <Icon
                name={isExpanded ? 'chevron-up' : 'chevron-down'}
                size={20}
                color={Colors.primary}
              />
            </TouchableOpacity>

            {isExpanded && (
              <View style={styles.accordionBody}>
                <Text style={styles.factorSubtitle}>{factor.subtitle}</Text>
                <Text style={styles.factorDescription}>{factor.description}</Text>

                {factor.planned && (
                  <Text style={styles.contributeText}>
                    If you have a solution to contribute, talk with Forseti.
                  </Text>
                )}

                <Text style={styles.factorsTitle}>Safety Factors:</Text>
                {factor.factors.map((item, index) => (
                  <Text key={index} style={styles.factorItem}>
                    • {item}
                  </Text>
                ))}
              </View>
            )}
          </View>
        );
      })}

      {/* Footer Note */}
      <View style={styles.footer}>
        <Text style={styles.footerText}>
          💡 Real-time monitoring, predictive analytics, and personalized recommendations across all
          dimensions
        </Text>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  contentContainer: {
    padding: 16,
    paddingBottom: 100,
  },
  header: {
    marginBottom: 20,
  },
  title: {
    fontSize: 28,
    fontWeight: 'bold',
    color: Colors.primary,
    marginBottom: 8,
    textAlign: 'center',
  },
  subtitle: {
    fontSize: 14,
    color: Colors.textSecondary,
    textAlign: 'center',
    lineHeight: 20,
  },
  infoCard: {
    backgroundColor: Colors.cardBackground,
    borderRadius: 12,
    padding: 16,
    marginBottom: 20,
    borderLeftWidth: 4,
    borderLeftColor: Colors.primary,
  },
  infoTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: Colors.primary,
    marginBottom: 8,
  },
  infoText: {
    fontSize: 14,
    color: Colors.text,
    lineHeight: 20,
  },
  sectionHeader: {
    marginBottom: 20,
  },
  sectionTitle: {
    fontSize: 22,
    fontWeight: 'bold',
    color: Colors.primary,
    marginBottom: 8,
  },
  sectionDescription: {
    fontSize: 14,
    color: Colors.textSecondary,
    lineHeight: 20,
  },
  accordionItem: {
    backgroundColor: Colors.cardBackground,
    borderRadius: 8,
    marginBottom: 12,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: Colors.border,
  },
  accordionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 16,
  },
  accordionHeaderContent: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  factorIcon: {
    width: 24,
    height: 24,
    marginRight: 12,
  },
  headerTextContainer: {
    flex: 1,
  },
  accordionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: Colors.text,
  },
  plannedBadge: {
    fontSize: 12,
    color: Colors.textSecondary,
    fontWeight: 'normal',
  },
  accordionBody: {
    padding: 16,
    paddingTop: 0,
    borderTopWidth: 1,
    borderTopColor: Colors.border,
  },
  factorSubtitle: {
    fontSize: 16,
    fontWeight: '600',
    color: Colors.primary,
    marginBottom: 12,
  },
  factorDescription: {
    fontSize: 14,
    color: Colors.text,
    lineHeight: 20,
    marginBottom: 16,
  },
  contributeText: {
    fontSize: 13,
    color: Colors.textSecondary,
    fontStyle: 'italic',
    marginBottom: 16,
  },
  factorsTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: Colors.primary,
    marginBottom: 8,
  },
  factorItem: {
    fontSize: 13,
    color: Colors.text,
    lineHeight: 20,
    marginBottom: 4,
    paddingLeft: 8,
  },
  footer: {
    marginTop: 20,
    padding: 16,
    backgroundColor: Colors.cardBackground,
    borderRadius: 8,
  },
  footerText: {
    fontSize: 13,
    color: Colors.textSecondary,
    lineHeight: 20,
    textAlign: 'center',
  },
});

export default SafetyScreen;
