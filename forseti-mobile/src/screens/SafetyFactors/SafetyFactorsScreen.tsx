/**
 * Safety Factors Screen - Understanding Safety Scores
 */

import React from 'react';
import { View, Text, StyleSheet, ScrollView } from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { Theme } from '../../utils/theme';

const { Colors, Spacing, Typography, Shadows } = Theme;

export const SafetyFactorsScreen: React.FC = () => {
  return (
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        <Icon name="information" size={48} color={Colors.primary} />
        <Text style={styles.headerTitle}>Safety Factors</Text>
        <Text style={styles.headerSubtitle}>
          Understanding how we calculate crime risk scores. Our comprehensive framework also
          evaluates safety across 7 dimensions (Safe, Energized, Connected, Free, Capable, Useful,
          Whole). Visit forseti.life/safety-factors for full details.
        </Text>
      </View>

      {/* Crime Type & Severity */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="alert-circle" size={24} color={Colors.riskHigh} />
          <Text style={styles.cardTitle}>Crime Type & Severity</Text>
        </View>
        <Text style={styles.cardText}>
          Different crime types have different weights in our safety calculations:
        </Text>
        <View style={styles.factorList}>
          <View style={styles.factorItem}>
            <Icon name="circle-small" size={20} color={Colors.riskCritical} />
            <Text style={styles.factorText}>
              <Text style={styles.bold}>Violent crimes</Text> (assault, robbery) - Highest weight
            </Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="circle-small" size={20} color={Colors.riskHigh} />
            <Text style={styles.factorText}>
              <Text style={styles.bold}>Property crimes</Text> (theft, burglary) - High weight
            </Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="circle-small" size={20} color={Colors.riskMedium} />
            <Text style={styles.factorText}>
              <Text style={styles.bold}>Quality of life</Text> (vandalism, noise) - Medium weight
            </Text>
          </View>
        </View>
      </View>

      {/* Time of Day */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="clock-outline" size={24} color={Colors.warning} />
          <Text style={styles.cardTitle}>Time of Day</Text>
        </View>
        <Text style={styles.cardText}>Crime patterns vary by time:</Text>
        <View style={styles.factorList}>
          <View style={styles.factorItem}>
            <Icon name="weather-night" size={20} color={Colors.riskHigh} />
            <Text style={styles.factorText}>
              <Text style={styles.bold}>Late night (10 PM - 4 AM)</Text> - Higher risk multiplier
            </Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="weather-sunset" size={20} color={Colors.riskMedium} />
            <Text style={styles.factorText}>
              <Text style={styles.bold}>Evening (6 PM - 10 PM)</Text> - Moderate risk
            </Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="white-balance-sunny" size={20} color={Colors.riskLow} />
            <Text style={styles.factorText}>
              <Text style={styles.bold}>Daytime (6 AM - 6 PM)</Text> - Lower risk
            </Text>
          </View>
        </View>
      </View>

      {/* Recency */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="calendar-clock" size={24} color={Colors.info} />
          <Text style={styles.cardTitle}>Historical Data & Trends</Text>
        </View>
        <Text style={styles.cardText}>Recent incidents weigh more heavily than older ones:</Text>
        <View style={styles.factorList}>
          <View style={styles.factorItem}>
            <Icon name="circle-small" size={20} color={Colors.riskHigh} />
            <Text style={styles.factorText}>
              <Text style={styles.bold}>Last 7 days</Text> - Highest weight
            </Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="circle-small" size={20} color={Colors.riskMedium} />
            <Text style={styles.factorText}>
              <Text style={styles.bold}>Last 30 days</Text> - High weight
            </Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="circle-small" size={20} color={Colors.riskLow} />
            <Text style={styles.factorText}>
              <Text style={styles.bold}>Last 90 days</Text> - Moderate weight
            </Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="circle-small" size={20} color={Colors.riskMinimal} />
            <Text style={styles.factorText}>
              <Text style={styles.bold}>Over 90 days</Text> - Lower weight
            </Text>
          </View>
        </View>
      </View>

      {/* Geographic Density */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="map-marker-radius" size={24} color={Colors.primary} />
          <Text style={styles.cardTitle}>Geographic Density</Text>
        </View>
        <Text style={styles.cardText}>We use H3 hexagons to analyze crime patterns:</Text>
        <View style={styles.factorList}>
          <View style={styles.factorItem}>
            <Icon name="hexagon-multiple" size={20} color={Colors.primary} />
            <Text style={styles.factorText}>Each hexagon is ~150 meters across</Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="hexagon-slice-3" size={20} color={Colors.primary} />
            <Text style={styles.factorText}>Crime clusters in a hexagon increase its risk</Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="hexagon-outline" size={20} color={Colors.primary} />
            <Text style={styles.factorText}>Surrounding hexagons affect your score</Text>
          </View>
        </View>
      </View>

      {/* Population Density */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="account-group" size={24} color={Colors.secondary} />
          <Text style={styles.cardTitle}>Population Density</Text>
        </View>
        <Text style={styles.cardText}>Crime rates are normalized by population:</Text>
        <View style={styles.factorList}>
          <View style={styles.factorItem}>
            <Icon name="circle-small" size={20} color={Colors.textPrimary} />
            <Text style={styles.factorText}>
              Areas with more people naturally have more incidents
            </Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="circle-small" size={20} color={Colors.textPrimary} />
            <Text style={styles.factorText}>
              We calculate crimes per capita for fair comparison
            </Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="circle-small" size={20} color={Colors.textPrimary} />
            <Text style={styles.factorText}>
              This ensures accurate risk assessment across neighborhoods
            </Text>
          </View>
        </View>
      </View>

      {/* Z-Score Methodology */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="chart-bell-curve" size={24} color={Colors.success} />
          <Text style={styles.cardTitle}>Statistical Analysis</Text>
        </View>
        <Text style={styles.cardText}>We use z-scores to determine risk levels:</Text>
        <View style={styles.factorList}>
          <View style={styles.factorItem}>
            <Icon name="circle-small" size={20} color={Colors.riskCritical} />
            <Text style={styles.factorText}>
              <Text style={styles.bold}>Critical:</Text> Z-score &gt; 2.0 (top 2.5%)
            </Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="circle-small" size={20} color={Colors.riskHigh} />
            <Text style={styles.factorText}>
              <Text style={styles.bold}>High:</Text> Z-score 1.0 - 2.0 (top 16%)
            </Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="circle-small" size={20} color={Colors.riskMedium} />
            <Text style={styles.factorText}>
              <Text style={styles.bold}>Medium:</Text> Z-score 0 - 1.0 (average)
            </Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="circle-small" size={20} color={Colors.riskLow} />
            <Text style={styles.factorText}>
              <Text style={styles.bold}>Low:</Text> Z-score -1.0 - 0 (below average)
            </Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="circle-small" size={20} color={Colors.success} />
            <Text style={styles.factorText}>
              <Text style={styles.bold}>Minimal:</Text> Z-score &lt; -1.0 (safest areas)
            </Text>
          </View>
        </View>
      </View>

      {/* AI Analysis */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="robot" size={24} color={Colors.primary} />
          <Text style={styles.cardTitle}>AI-Powered Insights</Text>
        </View>
        <Text style={styles.cardText}>Forseti's AI continuously learns and adapts:</Text>
        <View style={styles.factorList}>
          <View style={styles.factorItem}>
            <Icon name="brain" size={20} color={Colors.primary} />
            <Text style={styles.factorText}>Pattern recognition identifies emerging trends</Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="chart-timeline-variant" size={20} color={Colors.primary} />
            <Text style={styles.factorText}>Predictive modeling forecasts risk changes</Text>
          </View>
          <View style={styles.factorItem}>
            <Icon name="shield-check" size={20} color={Colors.primary} />
            <Text style={styles.factorText}>Real-time updates provide current safety status</Text>
          </View>
        </View>
      </View>

      <View style={styles.footer}>
        <Text style={styles.footerText}>
          Our methodology is continuously refined to provide the most accurate safety assessments
          for Philadelphia neighborhoods.
        </Text>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  bold: {
    fontWeight: Typography.fontWeight.semibold,
  },
  card: {
    backgroundColor: Colors.white,
    borderRadius: Spacing.borderRadius.lg,
    margin: Spacing.md,
    padding: Spacing.md,
    ...Shadows.md,
  },
  cardHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    marginBottom: Spacing.md,
  },
  cardText: {
    ...Typography.body,
    color: Colors.textSecondary,
    lineHeight: 24,
    marginBottom: Spacing.md,
  },
  cardTitle: {
    ...Typography.heading4,
    color: Colors.textPrimary,
    marginLeft: Spacing.sm,
  },
  container: {
    backgroundColor: Colors.background,
    flex: 1,
  },
  factorItem: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    marginBottom: Spacing.sm,
  },
  factorList: {
    marginTop: Spacing.sm,
  },
  factorText: {
    ...Typography.bodySmall,
    color: Colors.textPrimary,
    flex: 1,
    marginLeft: Spacing.xs,
  },
  footer: {
    padding: Spacing.xl,
    paddingBottom: Spacing.xxl,
  },
  footerText: {
    ...Typography.bodySmall,
    color: Colors.textSecondary,
    fontStyle: 'italic',
    lineHeight: 22,
    textAlign: 'center',
  },
  header: {
    alignItems: 'center',
    backgroundColor: Colors.backgroundDark,
    padding: Spacing.lg,
  },
  headerSubtitle: {
    ...Typography.body,
    color: Colors.textSecondary,
    textAlign: 'center',
  },
  headerTitle: {
    ...Typography.heading2,
    color: Colors.primary,
    marginBottom: Spacing.sm,
    marginTop: Spacing.md,
  },
});

export default SafetyFactorsScreen;
