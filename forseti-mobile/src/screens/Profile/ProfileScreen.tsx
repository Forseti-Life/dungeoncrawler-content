import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  Alert,
  TextInput,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import DrupalAuthService from '../../services/DrupalAuthService';
import { Colors } from '../../utils/colors';

const ProfileScreen: React.FC = ({ navigation }: any) => {
  const [user, setUser] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [showLogin, setShowLogin] = useState(false);
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [loggingIn, setLoggingIn] = useState(false);

  useEffect(() => {
    checkAuth();
  }, []);

  /**
   * Check if user is authenticated
   */
  const checkAuth = async () => {
    try {
      const currentUser = await DrupalAuthService.getCurrentUser();
      setUser(currentUser);
      setShowLogin(!currentUser);
    } catch (error) {
      console.error('Error checking auth:', error);
      setShowLogin(true);
    } finally {
      setLoading(false);
    }
  };

  /**
   * Handle login
   */
  const handleLogin = async () => {
    if (!username.trim() || !password) {
      Alert.alert('Error', 'Please enter username and password');
      return;
    }

    try {
      setLoggingIn(true);
      const result = await DrupalAuthService.login(username, password);
      setUser(result.user);
      setShowLogin(false);
      setUsername('');
      setPassword('');
      Alert.alert('Success', 'Welcome to Forseti!');
    } catch (error: any) {
      Alert.alert('Login Failed', error.message || 'Invalid username or password');
      console.error('Login error:', error);
    } finally {
      setLoggingIn(false);
    }
  };

  /**
   * Handle logout
   */
  const handleLogout = async () => {
    Alert.alert('Logout', 'Are you sure you want to sign out?', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Sign Out',
        style: 'destructive',
        onPress: async () => {
          try {
            await DrupalAuthService.logout();
            setUser(null);
            setShowLogin(true);
          } catch (error) {
            console.error('Logout error:', error);
          }
        },
      },
    ]);
  };

  /**
   * Navigate to conversation history
   */
  const goToConversations = () => {
    // TEMPORARILY DISABLED - AI Chat functionality
    // navigation.navigate('ConversationList');
    Alert.alert('Feature Unavailable', 'AI Chat conversations are temporarily disabled.');
  };

  if (loading) {
    return (
      <View style={styles.container}>
        <Text style={styles.placeholderText}>Loading...</Text>
      </View>
    );
  }

  if (showLogin) {
    return (
      <ScrollView style={styles.container} contentContainerStyle={styles.loginContainer}>
        <Icon name="shield-account" size={80} color={Colors.cyan} />
        <Text style={styles.loginTitle}>Welcome to Forseti</Text>
        <Text style={styles.loginSubtitle}>Sign in to access all features</Text>

        <View style={styles.form}>
          <View style={styles.inputGroup}>
            <Icon name="account" size={20} color={Colors.textSecondary} style={styles.inputIcon} />
            <TextInput
              style={styles.input}
              placeholder="Username"
              placeholderTextColor={Colors.textSecondary}
              value={username}
              onChangeText={setUsername}
              autoCapitalize="none"
              autoCorrect={false}
            />
          </View>

          <View style={styles.inputGroup}>
            <Icon name="lock" size={20} color={Colors.textSecondary} style={styles.inputIcon} />
            <TextInput
              style={styles.input}
              placeholder="Password"
              placeholderTextColor={Colors.textSecondary}
              value={password}
              onChangeText={setPassword}
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
            />
          </View>

          <TouchableOpacity
            style={[styles.loginButton, loggingIn && styles.loginButtonDisabled]}
            onPress={handleLogin}
            disabled={loggingIn}
          >
            <Text style={styles.loginButtonText}>{loggingIn ? 'Signing in...' : 'Sign In'}</Text>
          </TouchableOpacity>

          <Text style={styles.registerText}>
            Don't have an account? <Text style={styles.registerLink}>Register at forseti.life</Text>
          </Text>
        </View>
      </ScrollView>
    );
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.profileContainer}>
      {/* User Info */}
      <View style={styles.header}>
        <View style={styles.avatar}>
          <Icon name="account-circle" size={60} color={Colors.cyan} />
        </View>
        <Text style={styles.userName}>{user?.name || 'User'}</Text>
        <Text style={styles.userEmail}>{user?.mail || ''}</Text>
      </View>

      {/* Menu Options */}
      <View style={styles.menu}>
        {/* TEMPORARILY DISABLED - AI Chat functionality
        <TouchableOpacity style={styles.menuItem} onPress={goToConversations}>
          <Icon name="chat-processing" size={24} color={Colors.cyan} />
          <View style={styles.menuItemContent}>
            <Text style={styles.menuItemTitle}>My Conversations</Text>
            <Text style={styles.menuItemSubtitle}>View chat history with Forseti</Text>
          </View>
          <Icon name="chevron-right" size={24} color={Colors.textSecondary} />
        </TouchableOpacity>
        */}

        <TouchableOpacity style={styles.menuItem} onPress={() => navigation.navigate('Settings')}>
          <Icon name="cog" size={24} color={Colors.cyan} />
          <View style={styles.menuItemContent}>
            <Text style={styles.menuItemTitle}>Settings</Text>
            <Text style={styles.menuItemSubtitle}>App preferences and notifications</Text>
          </View>
          <Icon name="chevron-right" size={24} color={Colors.textSecondary} />
        </TouchableOpacity>

        <TouchableOpacity style={styles.menuItem} onPress={() => navigation.navigate('About')}>
          <Icon name="information" size={24} color={Colors.cyan} />
          <View style={styles.menuItemContent}>
            <Text style={styles.menuItemTitle}>About</Text>
            <Text style={styles.menuItemSubtitle}>Learn more about Forseti</Text>
          </View>
          <Icon name="chevron-right" size={24} color={Colors.textSecondary} />
        </TouchableOpacity>

        <TouchableOpacity style={styles.menuItem} onPress={() => navigation.navigate('HowItWorks')}>
          <Icon name="cog-outline" size={24} color={Colors.cyan} />
          <View style={styles.menuItemContent}>
            <Text style={styles.menuItemTitle}>How It Works</Text>
            <Text style={styles.menuItemSubtitle}>Technology and AI behind Forseti</Text>
          </View>
          <Icon name="chevron-right" size={24} color={Colors.textSecondary} />
        </TouchableOpacity>

        <TouchableOpacity style={styles.menuItem} onPress={() => navigation.navigate('Privacy')}>
          <Icon name="shield-lock" size={24} color={Colors.cyan} />
          <View style={styles.menuItemContent}>
            <Text style={styles.menuItemTitle}>Privacy & Security</Text>
            <Text style={styles.menuItemSubtitle}>How we protect your data</Text>
          </View>
          <Icon name="chevron-right" size={24} color={Colors.textSecondary} />
        </TouchableOpacity>

        <TouchableOpacity style={[styles.menuItem, styles.logoutItem]} onPress={handleLogout}>
          <Icon name="logout" size={24} color={Colors.danger} />
          <View style={styles.menuItemContent}>
            <Text style={[styles.menuItemTitle, styles.logoutText]}>Sign Out</Text>
          </View>
        </TouchableOpacity>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  avatar: {
    marginBottom: 16,
  },
  container: {
    backgroundColor: Colors.background,
    flex: 1,
  },
  form: {
    maxWidth: 400,
    width: '100%',
  },
  header: {
    alignItems: 'center',
    backgroundColor: Colors.card,
    borderRadius: 12,
    marginBottom: 24,
    paddingVertical: 32,
  },
  input: {
    color: Colors.text,
    flex: 1,
    fontSize: 16,
    height: 48,
  },
  inputGroup: {
    alignItems: 'center',
    backgroundColor: Colors.card,
    borderColor: Colors.border,
    borderRadius: 8,
    borderWidth: 1,
    flexDirection: 'row',
    marginBottom: 16,
    paddingHorizontal: 12,
  },
  inputIcon: {
    marginRight: 8,
  },
  loginButton: {
    alignItems: 'center',
    backgroundColor: Colors.cyan,
    borderRadius: 8,
    height: 48,
    justifyContent: 'center',
    marginTop: 8,
  },
  loginButtonDisabled: {
    opacity: 0.6,
  },
  loginButtonText: {
    color: Colors.background,
    fontSize: 16,
    fontWeight: '600',
  },
  loginContainer: {
    alignItems: 'center',
    padding: 24,
    paddingTop: 60,
  },
  loginSubtitle: {
    color: Colors.textSecondary,
    fontSize: 16,
    marginBottom: 40,
  },
  loginTitle: {
    color: Colors.text,
    fontSize: 28,
    fontWeight: '700',
    marginBottom: 8,
    marginTop: 24,
  },
  logoutItem: {
    borderBottomWidth: 0,
  },
  logoutText: {
    color: Colors.danger,
  },
  menu: {
    backgroundColor: Colors.card,
    borderRadius: 12,
    overflow: 'hidden',
  },
  menuItem: {
    alignItems: 'center',
    borderBottomColor: Colors.border,
    borderBottomWidth: 1,
    flexDirection: 'row',
    padding: 16,
  },
  menuItemContent: {
    flex: 1,
    marginLeft: 12,
  },
  menuItemSubtitle: {
    color: Colors.textSecondary,
    fontSize: 13,
  },
  menuItemTitle: {
    color: Colors.text,
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 2,
  },
  placeholderText: {
    color: Colors.textPrimary,
    fontSize: 24,
    fontWeight: 'bold',
    textAlign: 'center',
  },
  profileContainer: {
    padding: 16,
  },
  registerLink: {
    color: Colors.cyan,
    fontWeight: '600',
  },
  registerText: {
    color: Colors.textSecondary,
    fontSize: 14,
    marginTop: 24,
    textAlign: 'center',
  },
  userEmail: {
    color: Colors.textSecondary,
    fontSize: 14,
  },
  userName: {
    color: Colors.text,
    fontSize: 24,
    fontWeight: '700',
    marginBottom: 4,
  },
});

export default ProfileScreen;
