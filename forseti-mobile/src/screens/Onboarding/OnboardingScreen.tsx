/**
 * Background Monitoring Onboarding Screen
 *
 * Prompts user to enable background monitoring on first app launch
 */

import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  Image,
  ActivityIndicator,
} from 'react-native';
import { useBackgroundMonitoring } from '../../hooks/useBackgroundMonitoring';
import StorageService from '../../services/storage/StorageService';

interface OnboardingScreenProps {
  onComplete: () => void;
}

const OnboardingScreen: React.FC<OnboardingScreenProps> = ({ onComplete }) => {
  const [currentStep, setCurrentStep] = useState(0);
  const [isEnabling, setIsEnabling] = useState(false);
  const { startMonitoring } = useBackgroundMonitoring();

  const steps = [
    {
      title: 'Welcome to Forseti',
      description: 'Your personal safety companion that monitors crime patterns in real-time.',
      icon: '🛡️',
    },
    {
      title: 'Stay Protected 24/7',
      description:
        'Forseti continuously monitors your location and alerts you when entering high-crime areas.',
      icon: '📍',
    },
    {
      title: 'Enable Background Monitoring',
      description:
        'Allow Forseti to monitor your location in the background for continuous safety alerts.',
      icon: '🔔',
      action: true,
    },
  ];

  const handleNext = () => {
    if (currentStep < steps.length - 1) {
      setCurrentStep(currentStep + 1);
    }
  };

  const handleSkip = async () => {
    await StorageService.setItem('onboarding_completed', true);
    onComplete();
  };

  const handleEnableMonitoring = async () => {
    setIsEnabling(true);
    try {
      await startMonitoring();
      await StorageService.setItem('onboarding_completed', true);
      onComplete();
    } catch (error) {
      console.error('Failed to enable monitoring during onboarding:', error);
      // Still complete onboarding even if monitoring fails
      await StorageService.setItem('onboarding_completed', true);
      onComplete();
    } finally {
      setIsEnabling(false);
    }
  };

  const currentStepData = steps[currentStep];

  return (
    <View style={styles.container}>
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.iconContainer}>
          <Text style={styles.icon}>{currentStepData.icon}</Text>
        </View>

        <Text style={styles.title}>{currentStepData.title}</Text>
        <Text style={styles.description}>{currentStepData.description}</Text>

        {currentStepData.action && (
          <View style={styles.permissionInfo}>
            <Text style={styles.permissionTitle}>Why we need this permission:</Text>
            <Text style={styles.permissionText}>• Detect when you enter high-crime areas</Text>
            <Text style={styles.permissionText}>• Send safety alerts even when app is closed</Text>
            <Text style={styles.permissionText}>• Track your safety journey over time</Text>
            <Text style={styles.privacyNote}>
              Your location data is only stored locally on your device and is never sold or shared.
            </Text>
          </View>
        )}

        {/* Progress Dots */}
        <View style={styles.dotsContainer}>
          {steps.map((_, index) => (
            <View key={index} style={[styles.dot, index === currentStep && styles.activeDot]} />
          ))}
        </View>
      </ScrollView>

      {/* Action Buttons */}
      <View style={styles.buttonContainer}>
        {currentStepData.action ? (
          <>
            <TouchableOpacity
              style={styles.primaryButton}
              onPress={handleEnableMonitoring}
              disabled={isEnabling}
            >
              {isEnabling ? (
                <ActivityIndicator color="#000000" />
              ) : (
                <Text style={styles.primaryButtonText}>Enable Protection</Text>
              )}
            </TouchableOpacity>
            <TouchableOpacity style={styles.secondaryButton} onPress={handleSkip}>
              <Text style={styles.secondaryButtonText}>Skip for Now</Text>
            </TouchableOpacity>
          </>
        ) : (
          <>
            <TouchableOpacity style={styles.primaryButton} onPress={handleNext}>
              <Text style={styles.primaryButtonText}>Next</Text>
            </TouchableOpacity>
            <TouchableOpacity style={styles.secondaryButton} onPress={handleSkip}>
              <Text style={styles.secondaryButtonText}>Skip</Text>
            </TouchableOpacity>
          </>
        )}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  activeDot: {
    backgroundColor: '#00ff41',
    width: 24,
  },
  buttonContainer: {
    padding: 20,
  },
  container: {
    backgroundColor: '#000000',
    flex: 1,
  },
  content: {
    alignItems: 'center',
    flexGrow: 1,
    justifyContent: 'center',
    padding: 20,
  },
  description: {
    color: '#cccccc',
    fontSize: 16,
    lineHeight: 24,
    marginBottom: 40,
    textAlign: 'center',
  },
  dot: {
    backgroundColor: '#333333',
    borderRadius: 4,
    height: 8,
    marginHorizontal: 4,
    width: 8,
  },
  dotsContainer: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'center',
    marginTop: 20,
  },
  icon: {
    fontSize: 80,
  },
  iconContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 40,
  },
  permissionInfo: {
    backgroundColor: '#1a1a1a',
    borderColor: '#00ff41',
    borderRadius: 10,
    borderWidth: 1,
    marginTop: 20,
    padding: 20,
  },
  permissionText: {
    color: '#ffffff',
    fontSize: 14,
    lineHeight: 24,
    marginBottom: 8,
  },
  permissionTitle: {
    color: '#00ff41',
    fontSize: 16,
    fontWeight: 'bold',
    marginBottom: 12,
  },
  primaryButton: {
    alignItems: 'center',
    backgroundColor: '#00ff41',
    borderRadius: 10,
    justifyContent: 'center',
    marginBottom: 12,
    minHeight: 50,
    paddingVertical: 15,
  },
  primaryButtonText: {
    color: '#000000',
    fontSize: 16,
    fontWeight: 'bold',
  },
  privacyNote: {
    color: '#999999',
    fontSize: 12,
    fontStyle: 'italic',
    lineHeight: 18,
    marginTop: 12,
  },
  secondaryButton: {
    alignItems: 'center',
    backgroundColor: 'transparent',
    borderColor: '#00ff41',
    borderRadius: 10,
    borderWidth: 1,
    justifyContent: 'center',
    paddingVertical: 15,
  },
  secondaryButtonText: {
    color: '#00ff41',
    fontSize: 16,
  },
  title: {
    color: '#ffffff',
    fontSize: 28,
    fontWeight: 'bold',
    marginBottom: 20,
    textAlign: 'center',
  },
});

export default OnboardingScreen;
