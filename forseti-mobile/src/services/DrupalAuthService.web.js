/**
 * Web Mock for Drupal Authentication Service
 *
 * Provides a mock authentication service for web preview
 * In production, this would connect to the actual Drupal backend
 */

class DrupalAuthService {
  static instance = null;

  static getInstance() {
    if (!DrupalAuthService.instance) {
      DrupalAuthService.instance = new DrupalAuthService();
    }
    return DrupalAuthService.instance;
  }

  constructor() {
    this.baseUrl = 'https://forseti.life';
    this.currentUser = {
      uid: '0',
      username: 'guest',
      mail: 'guest@forseti.life',
      roles: ['anonymous'],
      display_name: 'Guest User',
    };
    this.sessionToken = 'mock-session-token';
    this.csrfToken = 'mock-csrf-token';

    console.log('🌐 DrupalAuthService (Web Mock) initialized');
  }

  /**
   * Initialize authentication from stored session
   */
  async initializeFromStorage() {
    console.log('📦 Mock: Initializing from storage');
    return Promise.resolve();
  }

  /**
   * Get CSRF token for authenticated requests
   */
  async getCsrfToken() {
    console.log('🔑 Mock: Getting CSRF token');
    return this.csrfToken;
  }

  /**
   * Get session cookie for authenticated requests
   */
  async getSessionCookie() {
    console.log('🍪 Mock: Getting session cookie');
    return this.sessionToken;
  }

  /**
   * Get current authenticated user
   */
  async getCurrentUser() {
    console.log('👤 Mock: Getting current user');
    return this.currentUser;
  }

  /**
   * Check if user is authenticated
   */
  isAuthenticated() {
    return true; // Always authenticated in mock
  }

  /**
   * Login user with username/password
   */
  async login(username, password) {
    console.log('🔐 Mock: Login attempt for', username);
    this.currentUser = {
      uid: '1',
      name: username,
      username: username,
      mail: `${username}@forseti.life`,
      roles: ['authenticated'],
      display_name: username,
    };
    return {
      success: true,
      user: this.currentUser,
      token: this.sessionToken,
      session: this.sessionToken,
    };
  }

  /**
   * Register new user
   */
  async register(username, email, password) {
    console.log('📝 Mock: Register new user', username);
    return {
      success: true,
      user: {
        uid: '1',
        username: username,
        mail: email,
        roles: ['authenticated'],
        display_name: username,
      },
    };
  }

  /**
   * Logout current user
   */
  async logout() {
    console.log('👋 Mock: Logout');
    this.currentUser = {
      uid: '0',
      username: 'guest',
      mail: 'guest@forseti.life',
      roles: ['anonymous'],
      display_name: 'Guest User',
    };
    return { success: true };
  }

  /**
   * Request password reset
   */
  async requestPasswordReset(email) {
    console.log('🔄 Mock: Password reset requested for', email);
    return {
      success: true,
      message: 'Password reset instructions sent (mock)',
    };
  }

  /**
   * Update user profile
   */
  async updateProfile(userId, profileData) {
    console.log('✏️ Mock: Update profile for user', userId);
    return {
      success: true,
      user: { ...this.currentUser, ...profileData },
    };
  }

  /**
   * Verify session is still valid
   */
  async verifySession() {
    console.log('✅ Mock: Session verified');
    return true;
  }

  /**
   * Refresh authentication tokens
   */
  async refreshTokens() {
    console.log('🔄 Mock: Tokens refreshed');
    return {
      session: this.sessionToken,
      csrf: this.csrfToken,
    };
  }
}

// Export the class so it can be instantiated
export { DrupalAuthService };
export default DrupalAuthService;
