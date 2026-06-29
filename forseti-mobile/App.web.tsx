/**
 * Forseti Mobile Application - Web Version
 * Main App Component for web preview (excludes native-only features)
 *
 * @format
 */

import React, { useEffect, useState } from 'react';
import {
  SafeAreaView,
  StatusBar,
  StyleSheet,
  useColorScheme,
  Alert,
  Platform,
  View,
  Text,
  Image,
  TouchableOpacity,
} from 'react-native';
import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createStackNavigator } from '@react-navigation/stack';

// Screens (excluding CrimeMapScreen for web)
import HomeScreen from './src/screens/Home/HomeScreen.web';
import CommunityScreen from './src/screens/Community/CommunityScreen.web';
import SafetyFactorsScreen from './src/screens/SafetyFactors/SafetyFactorsScreen';
import ProfileScreen from './src/screens/Profile/ProfileScreen';
import ChatScreen from './src/screens/Chat/ChatScreen';
import ConversationListScreen from './src/screens/Chat/ConversationListScreen';
import { AboutScreen } from './src/screens/About';
import { HowItWorksScreen } from './src/screens/HowItWorks';
import { PrivacyScreen } from './src/screens/Privacy';
import SettingsScreen from './src/screens/Settings/SettingsScreen';

// Utils
import { Colors } from './src/utils/colors';

const Tab = createBottomTabNavigator();
const Stack = createStackNavigator();

// Placeholder for Map on Web
const MapPlaceholderScreen = () => (
  <View
    style={{
      flex: 1,
      justifyContent: 'center',
      alignItems: 'center',
      backgroundColor: Colors.background,
    }}
  >
    <Text style={{ color: Colors.text, fontSize: 18, textAlign: 'center', padding: 20 }}>
      🗺️ Crime Map Feature{'\n\n'}
      Interactive maps are available in the native mobile app.{'\n\n'}
      This web preview shows other features of the Forseti app.
    </Text>
  </View>
);

// Chat Stack Navigator
function ChatStack() {
  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: {
          backgroundColor: Colors.primary,
        },
        headerTintColor: Colors.white,
        headerTitleStyle: {
          fontWeight: 'bold',
        },
      }}
    >
      <Stack.Screen
        name="ConversationList"
        component={ConversationListScreen}
        options={{ title: 'AI Conversations' }}
      />
      <Stack.Screen name="Chat" component={ChatScreen} options={{ title: 'Chat' }} />
    </Stack.Navigator>
  );
}

// Settings Stack Navigator
function SettingsStack() {
  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: {
          backgroundColor: Colors.primary,
        },
        headerTintColor: Colors.white,
        headerTitleStyle: {
          fontWeight: 'bold',
        },
      }}
    >
      <Stack.Screen
        name="SettingsMain"
        component={SettingsScreen}
        options={{ title: 'Settings' }}
      />
      <Stack.Screen name="About" component={AboutScreen} options={{ title: 'About Forseti' }} />
      <Stack.Screen
        name="HowItWorks"
        component={HowItWorksScreen}
        options={{ title: 'How It Works' }}
      />
      <Stack.Screen
        name="Privacy"
        component={PrivacyScreen}
        options={{ title: 'Privacy Policy' }}
      />
    </Stack.Navigator>
  );
}

// Import Icon component
import Icon from './src/components/Icon.web';

// Custom Tab Bar Icon component with modern styling
const TabIcon = ({
  iconName,
  focused,
  label,
  imageSource,
}: {
  iconName?: string;
  focused: boolean;
  label: string;
  imageSource?: any;
}) => {
  return (
    <View style={{ alignItems: 'center', justifyContent: 'center', paddingTop: 6 }}>
      <View
        style={{
          paddingHorizontal: 16,
          paddingVertical: 6,
          borderRadius: 20,
          backgroundColor: focused ? Colors.primary + '20' : 'transparent',
        }}
      >
        {imageSource ? (
          <Image
            source={imageSource}
            style={{
              width: 24,
              height: 24,
              tintColor: focused ? Colors.primary : Colors.textSecondary,
              opacity: focused ? 1 : 0.6,
            }}
            resizeMode="contain"
          />
        ) : (
          <Icon
            name={iconName || ''}
            size={24}
            color={focused ? Colors.primary : Colors.textSecondary}
          />
        )}
      </View>
      <Text
        style={{
          fontSize: 11,
          fontWeight: focused ? '600' : '400',
          color: focused ? Colors.primary : Colors.textSecondary,
          marginTop: 4,
        }}
      >
        {label}
      </Text>
    </View>
  );
};

function App(): React.JSX.Element {
  const isDarkMode = useColorScheme() === 'dark';
  const [navigationRef, setNavigationRef] = useState<any>(null);

  const navigationTheme = {
    ...DefaultTheme,
    colors: {
      ...DefaultTheme.colors,
      primary: Colors.primary,
      background: Colors.background,
      card: Colors.surface,
      text: Colors.text,
      border: Colors.border,
    },
  };

  useEffect(() => {
    console.log('Forseti Mobile (Web) - Application loaded');
  }, []);

  const navigateToHome = () => {
    if (navigationRef) {
      navigationRef.navigate('Home'); // Navigate to home screen (hidden from tabs)
    }
  };

  return (
    <View style={styles.container}>
      <StatusBar
        barStyle={isDarkMode ? 'light-content' : 'dark-content'}
        backgroundColor={Colors.primary}
      />
      {/* Custom Header with Logo */}
      <View style={styles.headerBar}>
        <TouchableOpacity onPress={navigateToHome} style={styles.logoContainer}>
          <Image
            source={require('./assets/images/forseti_logo_final.png')}
            style={styles.logo}
            resizeMode="contain"
          />
          <Text style={styles.logoText}>Forseti</Text>
        </TouchableOpacity>
      </View>
      <NavigationContainer theme={navigationTheme} ref={setNavigationRef}>
        <Tab.Navigator
          sceneContainerStyle={{ flex: 1 }}
          screenOptions={{
            tabBarStyle: {
              backgroundColor: Colors.surface,
              borderTopColor: Colors.border,
              borderTopWidth: 1,
              height: 70,
              paddingBottom: 8,
              paddingTop: 4,
            },
            tabBarShowLabel: false,
            tabBarActiveTintColor: Colors.primary,
            tabBarInactiveTintColor: Colors.textSecondary,
            headerShown: false, // Hide default header since we have custom one
          }}
        >
          {/* Hidden Home screen - accessed via logo click */}
          <Tab.Screen
            name="Home"
            component={HomeScreen}
            options={{
              tabBarButton: () => null, // Hide from tab bar
            }}
          />
          <Tab.Screen
            name="Map"
            component={MapPlaceholderScreen}
            options={{
              title: 'Safety Map',
              tabBarIcon: ({ focused }) => (
                <TabIcon iconName={focused ? 'map' : 'map-outline'} focused={focused} label="Map" />
              ),
            }}
          />
          <Tab.Screen
            name="ChatTab"
            component={ChatStack}
            options={{
              headerShown: false,
              title: 'AI Chat',
              tabBarIcon: ({ focused }) => (
                <TabIcon
                  imageSource={require('./assets/images/forseti_ai.png')}
                  focused={focused}
                  label="AI"
                />
              ),
            }}
          />
          <Tab.Screen
            name="Community"
            component={CommunityScreen}
            options={{
              title: 'Community',
              tabBarIcon: ({ focused }) => (
                <TabIcon
                  iconName={focused ? 'account-group' : 'account-group-outline'}
                  focused={focused}
                  label="Community"
                />
              ),
            }}
          />
          <Tab.Screen
            name="Safety"
            component={SafetyFactorsScreen}
            options={{
              title: 'Safety Factors',
              tabBarIcon: ({ focused }) => (
                <TabIcon
                  imageSource={require('./assets/images/forseti_safe.png')}
                  focused={focused}
                  label="Safety"
                />
              ),
            }}
          />
          <Tab.Screen
            name="Profile"
            component={SettingsStack}
            options={{
              headerShown: false,
              title: 'Profile',
              tabBarIcon: ({ focused }) => (
                <TabIcon
                  iconName={focused ? 'account' : 'account-outline'}
                  focused={focused}
                  label="Profile"
                />
              ),
            }}
          />
        </Tab.Navigator>
      </NavigationContainer>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: Colors.background,
    flex: 1,
    height: '100%',
    width: '100%',
  },
  headerBar: {
    backgroundColor: Colors.primary,
    height: 60,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 20,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  logoContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    cursor: 'pointer',
  },
  logo: {
    width: 40,
    height: 40,
    marginRight: 12,
  },
  logoText: {
    color: Colors.white,
    fontSize: 20,
    fontWeight: 'bold',
    letterSpacing: 0.5,
  },
});

export default App;
