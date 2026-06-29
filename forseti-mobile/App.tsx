/**
 * Forseti Mobile Application
 * Main App Component - Entry point for React Native application
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
  PermissionsAndroid,
  Platform,
  Image,
  View,
  Text,
  TouchableOpacity,
  AppState,
} from 'react-native';
import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createStackNavigator } from '@react-navigation/stack';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

// Screens
import HomeScreen from './src/screens/Home/HomeScreen';
import CrimeMapScreen from './src/screens/CrimeMapScreen';
import CommunityScreen from './src/screens/Community/CommunityScreen';
import SafetyScreen from './src/screens/Safety/SafetyScreen';
import ProfileScreen from './src/screens/Profile/ProfileScreen';
// TEMPORARILY DISABLED - AI Chat functionality
// import ChatScreen from './src/screens/Chat/ChatScreen';
// import ConversationListScreen from './src/screens/Chat/ConversationListScreen';
import { AboutScreen } from './src/screens/About';
import { HowItWorksScreen } from './src/screens/HowItWorks';
import { PrivacyScreen } from './src/screens/Privacy';
import SettingsScreen from './src/screens/Settings/SettingsScreen';
import DebugScreen from './src/screens/Debug/DebugScreen';
import DebugConsoleScreen from './src/screens/DebugConsole/DebugConsoleScreen';
import { SplashScreen } from './src/screens/Auth/SplashScreen';
import { LoginScreen } from './src/screens/Auth/LoginScreen';
import { RegisterScreen } from './src/screens/Auth/RegisterScreen';

// Services
import LocationService from './src/services/location/LocationService';
import StorageService from './src/services/storage/StorageService';
import NotificationService from './src/services/notifications/NotificationService';

// Utils
import { Colors } from './src/utils/colors';
import { requestLocationPermission } from './src/utils/permissions';
import APP_VERSION from './src/config/AppVersion';

// Debug: Log Colors on module load
console.log('🎨 [DEBUG] Colors object loaded:', Colors);
console.log('🎨 [DEBUG] Colors.background:', Colors?.background);
console.log('🎨 [DEBUG] Colors type:', typeof Colors);

const Tab = createBottomTabNavigator();
const Stack = createStackNavigator();

// Custom navigation theme using Forseti colors with fallbacks
const ForsetiNavigationTheme = {
  ...DefaultTheme,
  dark: true,
  colors: {
    ...DefaultTheme.colors,
    primary: Colors?.primary || '#00d4ff',
    background: Colors?.background || '#1a1a2e',
    card: Colors?.card || '#16213e',
    text: Colors?.text || '#ffffff',
    border: Colors?.border || '#2a3f5f',
    notification: Colors?.primary || '#00d4ff',
  },
};

// Main Tab Navigator with custom header
const TabNavigator = ({ navigation, onLogout }: { navigation: any; onLogout: () => void }) => {
  const handleLogout = async () => {
    Alert.alert('Logout', 'Are you sure you want to sign out?', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Sign Out',
        style: 'destructive',
        onPress: async () => {
          try {
            // Clear stored credentials
            await StorageService.setItem('userToken', '');
            await StorageService.setItem('userId', '');
            await StorageService.setItem('username', '');

            // Call logout callback to update app state
            onLogout();
          } catch (error) {
            console.error('Logout error:', error);
          }
        },
      },
    ]);
  };

  return (
    <>
      {/* Custom Header Bar with Forseti Logo */}
      <View style={styles.headerBar}>
        <TouchableOpacity onPress={() => navigation.navigate('Home')} style={styles.logoContainer}>
          <Image
            source={require('./assets/images/forseti_logo_final.png')}
            style={styles.logo}
            resizeMode="contain"
          />
          <View>
            <Text style={styles.logoText}>Forseti</Text>
            <Text style={styles.versionText}>{APP_VERSION.DISPLAY_VERSION}</Text>
          </View>
        </TouchableOpacity>
        <TouchableOpacity onPress={handleLogout} style={styles.logoutButton}>
          <Icon name="logout" size={24} color={Colors?.danger || '#F44336'} />
        </TouchableOpacity>
      </View>

      <Tab.Navigator
        screenOptions={({ route }) => ({
          tabBarIcon: ({ focused, color, size }) => {
            let iconName: string;

            switch (route.name) {
              case 'Home':
                iconName = focused ? 'home' : 'home-outline';
                break;
              case 'Map':
                iconName = focused ? 'map' : 'map-outline';
                break;
              case 'Chat':
                // Use custom Forseti AI branding icon (no tintColor for branded images)
                return (
                  <Image
                    source={require('./assets/images/forseti_chat.png')}
                    style={{
                      width: size,
                      height: size,
                      opacity: focused ? 1 : 0.6,
                    }}
                    resizeMode="contain"
                  />
                );
              case 'Safety':
                // Use custom Forseti safety branding icon (no tintColor for branded images)
                return (
                  <Image
                    source={require('./assets/images/forseti_safe.png')}
                    style={{
                      width: size,
                      height: size,
                      opacity: focused ? 1 : 0.6,
                    }}
                    resizeMode="contain"
                  />
                );
              case 'Profile':
                iconName = focused ? 'account' : 'account-outline';
                break;
              default:
                iconName = 'help-circle-outline';
            }

            return <Icon name={iconName} size={size} color={color} />;
          },
          tabBarActiveTintColor: Colors?.primary || '#00d4ff',
          tabBarInactiveTintColor: Colors?.gray || '#6c757d',
          tabBarStyle: styles.tabBar,
          headerShown: false, // Hide default header, we use custom header
        })}
      >
        <Tab.Screen name="Home" component={HomeScreen} options={{ title: 'Home' }} />
        <Tab.Screen name="Map" component={CrimeMapScreen} options={{ title: 'Map' }} />
        {/* TEMPORARILY DISABLED - AI Chat functionality */}
        {/* <Tab.Screen name="Chat" component={ChatScreen} options={{ title: 'AI' }} /> */}
        <Tab.Screen name="Safety" component={SafetyScreen} options={{ title: 'Safety' }} />
        <Tab.Screen name="Profile" component={ProfileScreen} options={{ title: 'Profile' }} />
      </Tab.Navigator>
    </>
  );
};

const App: React.FC = () => {
  const isDarkMode = useColorScheme() === 'dark';
  const [isInitialized, setIsInitialized] = useState(false);
  const [showSplash, setShowSplash] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [hasLocationPermission, setHasLocationPermission] = useState(false);
  const [hasNotificationPermission, setHasNotificationPermission] = useState(false);
  const [initError, setInitError] = useState<string | null>(null);

  const backgroundStyle = {
    backgroundColor: isDarkMode ? Colors?.darker || '#000000' : Colors?.lighter || '#ffffff',
    flex: 1,
  };

  useEffect(() => {
    console.log('🚀 Forseti Mobile App Starting');
    console.log('📱 Version:', APP_VERSION.DISPLAY_VERSION);
    console.log('🔧 Platform:', Platform.OS, Platform.Version);
    console.log('⚡ Build Date:', APP_VERSION.BUILD_DATE);
    initializeApp();

    // Listen for app state changes to check permissions when app becomes active
    const handleAppStateChange = (nextAppState: string) => {
      if (nextAppState === 'active') {
        console.log('📱 App became active - checking permissions...');
        checkPermissionsOnActive();
      }
    };

    const subscription = AppState.addEventListener('change', handleAppStateChange);

    // Cleanup subscription
    return () => {
      subscription?.remove();
    };
  }, []);

  const initializeApp = async () => {
    try {
      console.log('🚀 [INIT STEP 1] Starting Forseti Mobile App initialization...');

      // Initialize storage service
      try {
        console.log('🚀 [INIT STEP 2] Initializing storage service...');
        await StorageService.initialize();
        console.log('✅ [INIT STEP 2] Storage service initialized');
      } catch (error) {
        console.error('❌ [INIT STEP 2] Storage initialization failed:', error);
        setInitError(`Storage initialization failed: ${error}`);
        throw error;
      }

      // Initialize notification service
      try {
        console.log('🚀 [INIT STEP 3] Initializing notification service...');
        await NotificationService.initialize();
        console.log('✅ [INIT STEP 3] Notification service initialized');

        // Request notification permissions immediately after service init
        console.log('🚀 [INIT STEP 3a] Checking notification permissions...');
        const hasNotificationPermissions = await NotificationService.checkPermissions();
        setHasNotificationPermission(hasNotificationPermissions);
        
        if (!hasNotificationPermissions) {
          console.log('🔔 [INIT STEP 3a] Requesting notification permissions...');
          
          // Show explanation before requesting permissions
          await new Promise<void>((resolve) => {
            Alert.alert(
              '🔔 Enable Notifications',
              'Forseti needs notification permissions to alert you about safety conditions in your area. This helps keep you informed about potential risks.',
              [
                {
                  text: 'Skip',
                  style: 'cancel',
                  onPress: () => {
                    console.log('📝 [PERMISSIONS] User skipped notification permissions');
                    resolve();
                  }
                },
                {
                  text: 'Enable',
                  style: 'default',
                  onPress: async () => {
                    try {
                      const granted = await NotificationService.requestPermissions();
                      setHasNotificationPermission(granted);
                      console.log(`✅ [INIT STEP 3a] Notification permissions: ${granted}`);
                      if (!granted) {
                        console.warn('⚠️ [PERMISSIONS] Notification permissions denied');
                        // Show how to enable manually
                        Alert.alert(
                          'Notifications Disabled',
                          'You can enable notifications later in Settings > Notifications or in the app debug section.',
                          [{ text: 'OK' }]
                        );
                      }
                    } catch (permError) {
                      console.error('❌ [PERMISSIONS] Notification permission request failed:', permError);
                    }
                    resolve();
                  }
                }
              ]
            );
          });
        } else {
          console.log('✅ [INIT STEP 3a] Notification permissions already granted');
        }
      } catch (error) {
        console.error('❌ [INIT STEP 3] Notification service initialization failed:', error);
        // Don't throw - notifications are optional
      }

      // Check if user is already logged in
      try {
        console.log('🚀 [INIT STEP 4] Checking authentication status...');
        const userToken = await StorageService.getItem('userToken');
        if (userToken) {
          console.log('✅ [INIT STEP 4] User token found - auto-login');
          setIsAuthenticated(true);
        } else {
          console.log('ℹ️ [INIT STEP 4] No user token found - need login');
          setIsAuthenticated(false);
        }
      } catch (error) {
        console.error('❌ [INIT STEP 4] Auth check failed:', error);
        setIsAuthenticated(false);
      }

      // Request location permissions (only if authenticated)
      if (isAuthenticated) {
        try {
          console.log('🚀 [INIT STEP 5] Requesting location permissions...');
          const locationGranted = await requestLocationPermission();
          setHasLocationPermission(locationGranted);
          console.log(`✅ [INIT STEP 5] Location permission: ${locationGranted}`);

          if (locationGranted) {
            // Initialize location service
            try {
              console.log('🚀 [INIT STEP 6] Initializing location service...');
              await LocationService.initialize();
              console.log('✅ [INIT STEP 6] Location service initialized');
            } catch (error) {
              console.error('❌ [INIT STEP 6] Location service initialization failed:', error);
              // Don't throw - location is optional for viewing app
            }
          } else {
            console.warn(
              '⚠️ [INIT STEP 5] Location permission denied - continuing without location'
            );
          }
        } catch (error) {
          console.error('❌ [INIT STEP 5] Permission request failed:', error);
          // Don't throw - permissions can be requested later
        }
      }

      setIsInitialized(true);
      console.log('🎉 [INIT COMPLETE] Forseti Mobile App initialization complete!');
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : String(error);
      console.error('❌ [INIT FAILED] App initialization failed:', error);
      console.error(
        '❌ [INIT FAILED] Error stack:',
        error instanceof Error ? error.stack : 'No stack trace'
      );
      setInitError(`App initialization failed: ${errorMessage}`);
      setIsInitialized(true); // Still show UI with error message
    }
  };

  const checkPermissionsOnActive = async () => {
    try {
      // Check notification permissions without prompting
      const hasNotifications = await NotificationService.checkPermissions();
      const currentNotificationState = hasNotificationPermission;
      
      setHasNotificationPermission(hasNotifications);
      
      // If permissions changed, log it
      if (hasNotifications !== currentNotificationState) {
        console.log(`🔔 [PERMISSIONS] Notification permission changed: ${currentNotificationState} → ${hasNotifications}`);
        
        if (hasNotifications && !currentNotificationState) {
          console.log('🎉 [PERMISSIONS] Notifications enabled - app now has full functionality');
        } else if (!hasNotifications && currentNotificationState) {
          console.log('⚠️ [PERMISSIONS] Notifications disabled - safety alerts may not work');
        }
      }
    } catch (error) {
      console.error('❌ [PERMISSIONS] Failed to check permissions on app active:', error);
    }
  };

  const handleSplashFinish = () => {
    setShowSplash(false);
  };

  const handleLoginSuccess = () => {
    setIsAuthenticated(true);
    // Re-initialize location services after login
    initializeApp();
  };

  const handleLogout = () => {
    setIsAuthenticated(false);
  };

  // Show splash screen first
  if (showSplash) {
    return <SplashScreen onFinish={handleSplashFinish} />;
  }

  // Show error screen if initialization failed
  if (initError && !isAuthenticated) {
    const Text = require('react-native').Text;
    const View = require('react-native').View;
    const ScrollView = require('react-native').ScrollView;
    const Button = require('react-native').Button;
    return (
      <SafeAreaView style={[backgroundStyle, { flex: 1 }]}>
        <StatusBar
          barStyle={isDarkMode ? 'light-content' : 'dark-content'}
          backgroundColor="#EF4444"
        />
        <ScrollView style={{ flex: 1, padding: 20 }}>
          <View
            style={{ backgroundColor: '#FEE2E2', padding: 16, borderRadius: 8, marginBottom: 16 }}
          >
            <Text style={{ fontSize: 20, fontWeight: 'bold', color: '#DC2626', marginBottom: 8 }}>
              ⚠️ Initialization Error
            </Text>
            <Text style={{ fontSize: 14, color: '#991B1B', marginBottom: 16 }}>{initError}</Text>
            <Button
              title="Retry"
              onPress={() => {
                setInitError(null);
                setIsInitialized(false);
                initializeApp();
              }}
              color="#DC2626"
            />
          </View>
          <View style={{ backgroundColor: '#F3F4F6', padding: 16, borderRadius: 8 }}>
            <Text style={{ fontSize: 16, fontWeight: 'bold', color: '#374151', marginBottom: 8 }}>
              Debug Information
            </Text>
            <Text style={{ fontSize: 12, color: '#6B7280', fontFamily: 'monospace' }}>
              Check console logs for detailed error information.
            </Text>
          </View>
        </ScrollView>
      </SafeAreaView>
    );
  }

  // Show auth screens if not authenticated
  if (!isAuthenticated && isInitialized) {
    return (
      <SafeAreaView style={backgroundStyle}>
        <StatusBar
          barStyle={isDarkMode ? 'light-content' : 'dark-content'}
          backgroundColor={Colors?.primary || '#00d4ff'}
        />
        <NavigationContainer theme={ForsetiNavigationTheme}>
          <Stack.Navigator
            screenOptions={{
              headerShown: false,
            }}
          >
            <Stack.Screen name="Login">
              {props => <LoginScreen {...props} onLoginSuccess={handleLoginSuccess} />}
            </Stack.Screen>
            <Stack.Screen name="Register" component={RegisterScreen} />
          </Stack.Navigator>
        </NavigationContainer>
      </SafeAreaView>
    );
  }

  // Show main app if authenticated
  return (
    <SafeAreaView style={backgroundStyle}>
      <StatusBar
        barStyle={isDarkMode ? 'light-content' : 'dark-content'}
        backgroundColor={Colors?.background || '#1a1a2e'}
      />
      <NavigationContainer theme={ForsetiNavigationTheme}>
        <Stack.Navigator
          screenOptions={{
            headerShown: false,
          }}
        >
          <Stack.Screen name="MainTabs">
            {props => <TabNavigator {...props} onLogout={handleLogout} />}
          </Stack.Screen>
          {/* Disabled Chat functionality
          <Stack.Screen
            name="ConversationList"
            component={ConversationListScreen}
            options={{
              headerShown: true,
              headerStyle: { backgroundColor: Colors?.primary || '#00d4ff' },
              headerTintColor: Colors?.white || '#ffffff',
              headerTitle: 'Conversations',
            }}
          />
          */}
          <Stack.Screen
            name="About"
            component={AboutScreen}
            options={{
              headerShown: true,
              headerStyle: { backgroundColor: Colors?.primary || '#00d4ff' },
              headerTintColor: Colors?.white || '#ffffff',
              headerTitle: 'About Forseti',
            }}
          />
          <Stack.Screen
            name="HowItWorks"
            component={HowItWorksScreen}
            options={{
              headerShown: true,
              headerStyle: { backgroundColor: Colors?.primary || '#00d4ff' },
              headerTintColor: Colors?.white || '#ffffff',
              headerTitle: 'How It Works',
            }}
          />
          <Stack.Screen
            name="Privacy"
            component={PrivacyScreen}
            options={{
              headerShown: true,
              headerStyle: { backgroundColor: Colors?.primary || '#00d4ff' },
              headerTintColor: Colors?.white || '#ffffff',
              headerTitle: 'Privacy & Security',
            }}
          />
          <Stack.Screen
            name="Settings"
            component={SettingsScreen}
            options={{
              headerShown: true,
              headerStyle: { backgroundColor: Colors?.primary || '#00d4ff' },
              headerTintColor: Colors?.white || '#ffffff',
              headerTitle: 'Settings',
            }}
          />
          <Stack.Screen
            name="Debug"
            component={DebugScreen}
            options={{
              headerShown: true,
              headerStyle: { backgroundColor: Colors?.primary || '#00d4ff' },
              headerTintColor: Colors?.white || '#ffffff',
              headerTitle: 'Debug Tools',
            }}
          />
          <Stack.Screen
            name="DebugConsole"
            component={DebugConsoleScreen}
            options={{
              headerShown: false,
            }}
          />
        </Stack.Navigator>
      </NavigationContainer>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  headerBar: {
    alignItems: 'center',
    backgroundColor: Colors?.background || '#1a1a2e',
    borderBottomColor: Colors?.border || '#2a3f5f',
    borderBottomWidth: 1,
    flexDirection: 'row',
    height: 60,
    justifyContent: 'space-between',
    paddingHorizontal: 16,
  },
  logo: {
    height: 40,
    marginRight: 8,
    width: 40,
  },
  logoContainer: {
    alignItems: 'center',
    flexDirection: 'row',
  },
  logoText: {
    color: Colors?.text || '#ffffff',
    fontSize: 20,
    fontWeight: 'bold',
  },
  logoutButton: {
    padding: 8,
  },
  versionText: {
    color: Colors?.textSecondary || '#9CA3AF',
    fontSize: 10,
    marginTop: -2,
  },
  tabBar: {
    backgroundColor: Colors?.card || '#16213e',
    borderTopColor: Colors?.border || '#2a3f5f',
    borderTopWidth: 1,
    height: Platform.OS === 'ios' ? 85 : 60,
    paddingBottom: Platform.OS === 'ios' ? 20 : 5,
    paddingTop: 5,
  },
});

export default App;
