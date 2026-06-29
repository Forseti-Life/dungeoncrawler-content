/**
 * Profile Screen - Web Version
 * Displays user information and settings with link to edit on forseti.life
 */

import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  ActivityIndicator,
  Linking,
  Platform,
} from 'react-native';
import Icon from '../../components/Icon.web';
import { Colors } from '../../utils/colors';
import storageService from '../../services/storage/StorageService';
import { DrupalAuthService } from '../../services/DrupalAuthService';

const ProfileScreen: React.FC = () => {
  const [user, setUser] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadUserData();
  }, []);

  const loadUserData = async () => {
    try {
      // Get user data from auth service
      const authService = DrupalAuthService.getInstance();
      const currentUser = authService.getCurrentUser();

      if (currentUser) {
        setUser(currentUser);
      } else {
        // Fallback to storage
        const username = await storageService.getItem('username');
        const userId = await storageService.getItem('userId');

        if (username) {
          setUser({
            name: username,
            uid: userId,
            mail: `${username}@forseti.life`,
          });
        }
      }
    } catch (error) {
      console.error('Error loading user data:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleEditProfile = () => {
    const url = 'https://forseti.life/user';
    Linking.openURL(url).catch(err => console.error('Error opening URL:', err));
  };

  const handleLogout = async () => {
    if (Platform.OS === 'web') {
      const confirmed = window.confirm('Are you sure you want to sign out?');
      if (!confirmed) return;
    }

    try {
      // Clear storage
      await storageService.removeItem('userToken');
      await storageService.removeItem('userId');
      await storageService.removeItem('username');

      // Reload page to go back to login
      if (Platform.OS === 'web') {
        window.location.reload();
      }
    } catch (error) {
      console.error('Logout error:', error);
    }
  };

  if (loading) {
    return (
      <View style={styles.container}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={Colors.primary} />
          <Text style={styles.loadingText}>Loading profile...</Text>
        </View>
      </View>
    );
  }

  const menuItems = [
    {
      icon: 'account-edit',
      label: 'Edit Profile',
      subtitle: 'Update your information on forseti.life',
      onPress: handleEditProfile,
      color: Colors.primary,
    },
    {
      icon: 'shield-account',
      label: 'Privacy & Security',
      subtitle: 'Control your data and privacy',
      onPress: () => Linking.openURL('https://forseti.life/privacy'),
      color: '#4CAF50',
    },
    {
      icon: 'help-circle-outline',
      label: 'Help & Support',
      subtitle: 'Get assistance and report issues',
      onPress: () => Linking.openURL('https://forseti.life/talk-with-forseti'),
      color: '#9C27B0',
    },
    {
      icon: 'information-outline',
      label: 'About Forseti',
      subtitle: 'Learn more about our mission',
      onPress: () => Linking.openURL('https://forseti.life/about'),
      color: '#2196F3',
    },
  ];

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.contentContainer}>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Profile</Text>
      </View>

      {/* User Info Card */}
      <View style={styles.userCard}>
        <View style={styles.avatarContainer}>
          <View style={styles.avatar}>
            <Icon name="account" size={48} color={Colors.primary} />
          </View>
        </View>
        <Text style={styles.username}>{user?.name || user?.username || 'User'}</Text>
        <Text style={styles.email}>{user?.mail || 'user@forseti.life'}</Text>
        {user?.uid && <Text style={styles.userId}>User ID: {user.uid}</Text>}

        <TouchableOpacity style={styles.editButton} onPress={handleEditProfile}>
          <Icon name="pencil" size={16} color={Colors.primary} />
          <Text style={styles.editButtonText}>Edit Profile on forseti.life</Text>
        </TouchableOpacity>
      </View>

      {/* Account Stats */}
      <View style={styles.statsContainer}>
        <View style={styles.statItem}>
          <Text style={styles.statValue}>0</Text>
          <Text style={styles.statLabel}>Reports</Text>
        </View>
        <View style={styles.statDivider} />
        <View style={styles.statItem}>
          <Text style={styles.statValue}>0</Text>
          <Text style={styles.statLabel}>Alerts</Text>
        </View>
        <View style={styles.statDivider} />
        <View style={styles.statItem}>
          <Text style={styles.statValue}>0</Text>
          <Text style={styles.statLabel}>Saved</Text>
        </View>
      </View>

      {/* Menu Items */}
      <View style={styles.menuContainer}>
        {menuItems.map((item, index) => (
          <TouchableOpacity key={index} style={styles.menuItem} onPress={item.onPress}>
            <View style={[styles.menuIcon, { backgroundColor: item.color + '20' }]}>
              <Icon name={item.icon} size={24} color={item.color} />
            </View>
            <View style={styles.menuTextContainer}>
              <Text style={styles.menuLabel}>{item.label}</Text>
              <Text style={styles.menuSubtitle}>{item.subtitle}</Text>
            </View>
            <Icon name="chevron-right" size={24} color={Colors.textSecondary} />
          </TouchableOpacity>
        ))}
      </View>

      {/* Logout Button */}
      <TouchableOpacity style={styles.logoutButton} onPress={handleLogout}>
        <Icon name="logout" size={20} color="#F44336" />
        <Text style={styles.logoutText}>Sign Out</Text>
      </TouchableOpacity>

      {/* Footer */}
      <View style={styles.footer}>
        <Text style={styles.footerText}>Forseti Mobile v1.0.0</Text>
        <Text style={styles.footerText}>Powered by H3 Geospatial Intelligence</Text>
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
    paddingBottom: 100,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 16,
    color: Colors.text,
    fontSize: 16,
  },
  header: {
    padding: 16,
    paddingTop: 20,
  },
  headerTitle: {
    fontSize: 24,
    fontWeight: 'bold',
    color: Colors.text,
  },
  userCard: {
    backgroundColor: Colors.card,
    margin: 12,
    borderRadius: 12,
    padding: 16,
    alignItems: 'center',
  },
  avatarContainer: {
    marginBottom: 12,
  },
  avatar: {
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: Colors.primary + '20',
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 2,
    borderColor: Colors.primary,
  },
  username: {
    fontSize: 18,
    fontWeight: 'bold',
    color: Colors.text,
    marginBottom: 4,
  },
  email: {
    fontSize: 12,
    color: Colors.textSecondary,
    marginBottom: 2,
  },
  userId: {
    fontSize: 10,
    color: Colors.textSecondary,
    marginBottom: 12,
  },
  editButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.primary + '20',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 16,
    gap: 6,
  },
  editButtonText: {
    color: Colors.primary,
    fontSize: 12,
    fontWeight: '600',
  },
  statsContainer: {
    flexDirection: 'row',
    backgroundColor: Colors.card,
    marginHorizontal: 12,
    marginBottom: 12,
    borderRadius: 12,
    padding: 12,
    justifyContent: 'space-around',
  },
  statItem: {
    alignItems: 'center',
    flex: 1,
  },
  statDivider: {
    width: 1,
    backgroundColor: Colors.border,
  },
  statValue: {
    fontSize: 18,
    fontWeight: 'bold',
    color: Colors.primary,
    marginBottom: 2,
  },
  statLabel: {
    fontSize: 12,
    color: Colors.textSecondary,
  },
  menuContainer: {
    backgroundColor: Colors.card,
    marginHorizontal: 12,
    marginBottom: 12,
    borderRadius: 12,
    padding: 4,
  },
  menuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 8,
    borderRadius: 8,
  },
  menuIcon: {
    width: 36,
    height: 36,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 10,
  },
  menuTextContainer: {
    flex: 1,
  },
  menuLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: Colors.text,
    marginBottom: 2,
  },
  menuSubtitle: {
    fontSize: 11,
    color: Colors.textSecondary,
  },
  logoutButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.card,
    marginHorizontal: 12,
    marginBottom: 12,
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#F44336',
    gap: 6,
  },
  logoutText: {
    color: '#F44336',
    fontSize: 14,
    fontWeight: '600',
  },
  footer: {
    alignItems: 'center',
    padding: 24,
  },
  footerText: {
    fontSize: 12,
    color: Colors.textSecondary,
    marginBottom: 4,
  },
});

export default ProfileScreen;
