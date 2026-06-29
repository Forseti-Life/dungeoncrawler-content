import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  ActivityIndicator,
  Alert,
  Image,
  Linking,
} from 'react-native';
import { DrupalAuthService } from '../../services/DrupalAuthService';
import storageService from '../../services/storage/StorageService';
import { Theme } from '../../utils/theme';

const { Colors, Spacing, Typography } = Theme;

interface LoginScreenProps {
  navigation: any;
  onLoginSuccess?: () => void;
}

export const LoginScreen: React.FC<LoginScreenProps> = ({ navigation, onLoginSuccess }) => {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  const handleLogin = async () => {
    console.log('Login attempt:', { username, password: '***' });

    if (!username || !password) {
      if (Platform.OS === 'web') {
        window.alert('Please enter both username and password');
      } else {
        Alert.alert('Error', 'Please enter both username and password');
      }
      return;
    }

    setLoading(true);
    console.log('Starting login process...');

    try {
      const authService = DrupalAuthService.getInstance();
      console.log('Auth service:', authService);

      const result = await authService.login(username, password);
      console.log('Login result:', result);

      if (result.success) {
        // Store user session
        await storageService.setItem('userToken', result.token);
        await storageService.setItem('userId', result.user.uid.toString());
        await storageService.setItem('username', result.user.name);

        console.log('Login success, calling onLoginSuccess');
        if (onLoginSuccess) {
          onLoginSuccess();
        } else {
          if (Platform.OS === 'web') {
            window.alert('Welcome to Forseti!');
          } else {
            Alert.alert('Success', 'Welcome to Forseti!', [
              {
                text: 'OK',
                onPress: () => navigation.replace('Home'),
              },
            ]);
          }
        }
      } else {
        console.error('Login failed:', result.message);
        if (Platform.OS === 'web') {
          window.alert('Login Failed: ' + (result.message || 'Invalid credentials'));
        } else {
          Alert.alert('Login Failed', result.message || 'Invalid credentials');
        }
      }
    } catch (error) {
      console.error('Login error:', error);
      if (Platform.OS === 'web') {
        window.alert('An error occurred during login. Please try again.');
      } else {
        Alert.alert('Error', 'An error occurred during login. Please try again.');
      }
    } finally {
      setLoading(false);
      console.log('Login process complete');
    }
  };

  const handleRegister = () => {
    navigation.navigate('Register');
  };

  const handleForgotPassword = () => {
    Alert.alert(
      'Password Reset',
      'Please visit https://forseti.life/user/password to reset your password.'
    );
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView contentContainerStyle={styles.scrollContent} keyboardShouldPersistTaps="handled">
        <View style={styles.header}>
          <View style={styles.logoWrapper}>
            <Image
              source={{ uri: '/forseti-logo.png' }}
              style={styles.logoImage}
              resizeMode="contain"
            />
          </View>
          <Text style={styles.title}>Forseti</Text>
          <Text style={styles.subtitle}>Stay Informed, Stay Safe</Text>
        </View>

        <View style={styles.formContainer}>
          <View style={styles.inputContainer}>
            <Text style={styles.label}>Username or Email</Text>
            <TextInput
              style={styles.input}
              placeholder="Enter your username or email"
              placeholderTextColor={Colors.textSecondary}
              value={username}
              onChangeText={setUsername}
              autoCapitalize="none"
              autoCorrect={false}
              editable={!loading}
            />
          </View>

          <View style={styles.inputContainer}>
            <Text style={styles.label}>Password</Text>
            <TextInput
              style={styles.input}
              placeholder="Enter your password"
              placeholderTextColor={Colors.textSecondary}
              value={password}
              onChangeText={setPassword}
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
              editable={!loading}
            />
          </View>

          <TouchableOpacity
            onPress={handleForgotPassword}
            disabled={loading}
            style={styles.forgotPasswordContainer}
          >
            <Text style={styles.forgotPasswordText}>Forgot Password?</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.loginButton, loading && styles.buttonDisabled]}
            onPress={handleLogin}
            disabled={loading}
          >
            {loading ? (
              <ActivityIndicator color="#FFFFFF" />
            ) : (
              <Text style={styles.loginButtonText}>Sign In</Text>
            )}
          </TouchableOpacity>

          <View style={styles.divider}>
            <View style={styles.dividerLine} />
            <Text style={styles.dividerText}>OR</Text>
            <View style={styles.dividerLine} />
          </View>

          <TouchableOpacity
            style={styles.registerButton}
            onPress={handleRegister}
            disabled={loading}
          >
            <Text style={styles.registerButtonText}>Create New Account</Text>
          </TouchableOpacity>

          <View style={styles.footer}>
            <Text style={styles.footerText}>
              By signing in, you agree to our{' '}
              <Text
                style={styles.linkText}
                onPress={() => Linking.openURL('https://forseti.life/terms-of-service')}
              >
                Terms of Service
              </Text>{' '}
              and{' '}
              <Text
                style={styles.linkText}
                onPress={() => Linking.openURL('https://forseti.life/privacy-policy')}
              >
                Privacy Policy
              </Text>
            </Text>
          </View>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  buttonDisabled: {
    opacity: 0.6,
  },
  container: {
    backgroundColor: Colors?.background || '#1a1a2e',
    flex: 1,
  },
  divider: {
    alignItems: 'center',
    flexDirection: 'row',
    marginVertical: 24,
  },
  dividerLine: {
    backgroundColor: Colors.border,
    flex: 1,
    height: 1,
  },
  dividerText: {
    color: Colors.textSecondary,
    fontSize: 14,
    fontWeight: '600',
    marginHorizontal: 16,
  },
  footer: {
    alignItems: 'center',
    paddingTop: 20,
  },
  footerText: {
    color: Colors.textSecondary,
    fontSize: 12,
    lineHeight: 18,
    textAlign: 'center',
  },
  forgotPasswordContainer: {
    alignSelf: 'flex-end',
    marginBottom: 24,
  },
  forgotPasswordText: {
    color: Colors.primary,
    fontSize: 14,
    fontWeight: '600',
  },
  formContainer: {
    width: '100%',
  },
  header: {
    alignItems: 'center',
    marginBottom: 40,
  },
  input: {
    backgroundColor: '#FFFFFF',
    borderColor: Colors.border,
    borderRadius: 12,
    borderWidth: 1,
    color: '#000000',
    fontSize: 16,
    paddingHorizontal: 16,
    paddingVertical: 14,
  },
  inputContainer: {
    marginBottom: 20,
  },
  label: {
    color: Colors.text,
    fontSize: 14,
    fontWeight: '600',
    marginBottom: 8,
  },
  loginButton: {
    alignItems: 'center',
    backgroundColor: Colors.primary,
    borderRadius: 12,
    elevation: 4,
    marginBottom: 20,
    paddingVertical: 16,
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
  },
  loginButtonText: {
    color: '#FFFFFF',
    fontSize: 16,
    fontWeight: 'bold',
  },
  logo: {
    fontSize: 64,
    marginBottom: 16,
  },
  logoWrapper: {
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
    position: 'relative',
  },
  logoImage: {
    width: 120,
    height: 120,
    marginBottom: 8,
  },
  registerButton: {
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    borderColor: Colors.primary,
    borderRadius: 12,
    borderWidth: 2,
    marginBottom: 24,
    paddingVertical: 16,
  },
  registerButtonText: {
    color: Colors.primary,
    fontSize: 16,
    fontWeight: 'bold',
  },
  scrollContent: {
    flexGrow: 1,
    justifyContent: 'center',
    padding: 24,
  },
  subtitle: {
    color: Colors.textSecondary,
    fontSize: 16,
  },
  title: {
    color: Colors.primary,
    fontSize: 32,
    fontWeight: 'bold',
    marginBottom: 8,
  },
  linkText: {
    color: Colors.primary,
    textDecorationLine: 'underline',
  },
});
