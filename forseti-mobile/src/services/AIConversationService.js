/**
 * AI Conversation Service for Forseti Mobile
 *
 * Integrates with Drupal's ai_conversation module
 * Provides AI-powered conversations with Forseti
 */

import axios from 'axios';
import DrupalAuthService from './DrupalAuthService';

class AIConversationService {
  static instance = null;

  static getInstance() {
    if (!AIConversationService.instance) {
      AIConversationService.instance = new AIConversationService();
    }
    return AIConversationService.instance;
  }

  constructor() {
    this.baseUrl = 'https://forseti.life'; // Main Forseti site
    this.authService = DrupalAuthService.getInstance();
  }

  /**
   * Create a new AI conversation
   * @returns {Promise<Object>} Conversation object with ID
   */
  async createConversation() {
    try {
      const user = await this.authService.getCurrentUser();

      if (!user) {
        throw new Error('User must be authenticated to create conversation');
      }

      const csrfToken = await this.authService.getCsrfToken();
      const sessionCookie = await this.authService.getSessionCookie();

      const response = await axios.post(
        `${this.baseUrl}/api/ai-conversation/create`,
        {
          title: `Conversation with Forseti - ${new Date().toLocaleString()}`,
          ai_model: 'claude-3-5-sonnet-20241022',
        },
        {
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
            Cookie: sessionCookie,
          },
          timeout: 10000,
        }
      );

      console.log('✅ Created AI conversation:', response.data.conversation_id);
      return response.data;
    } catch (error) {
      console.error('❌ Error creating conversation:', error.message);
      throw error;
    }
  }

  /**
   * Send a message in a conversation
   * @param {number} conversationId - The conversation node ID
   * @param {string} message - User's message
   * @returns {Promise<Object>} AI response
   */
  async sendMessage(conversationId, message) {
    try {
      const csrfToken = await this.authService.getCsrfToken();
      const sessionCookie = await this.authService.getSessionCookie();

      console.log(`📤 Sending message to conversation ${conversationId}`);

      const response = await axios.post(
        `${this.baseUrl}/api/ai-conversation/${conversationId}/message`,
        {
          message: message.trim(),
        },
        {
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
            Cookie: sessionCookie,
          },
          timeout: 60000, // AI responses can take time
        }
      );

      console.log('✅ Received AI response');
      return response.data;
    } catch (error) {
      console.error('❌ Error sending message:', error.message);
      throw error;
    }
  }

  /**
   * Get conversation history
   * @param {number} conversationId - The conversation node ID
   * @returns {Promise<Array>} Array of messages
   */
  async getConversationHistory(conversationId) {
    try {
      const csrfToken = await this.authService.getCsrfToken();
      const sessionCookie = await this.authService.getSessionCookie();

      const response = await axios.get(
        `${this.baseUrl}/api/ai-conversation/${conversationId}/messages`,
        {
          headers: {
            'X-CSRF-Token': csrfToken,
            Cookie: sessionCookie,
          },
          timeout: 10000,
        }
      );

      return response.data.messages || [];
    } catch (error) {
      console.error('❌ Error fetching conversation history:', error.message);
      throw error;
    }
  }

  /**
   * Get user's conversations
   * @returns {Promise<Array>} Array of conversation objects
   */
  async getUserConversations() {
    try {
      const user = await this.authService.getCurrentUser();

      if (!user) {
        return [];
      }

      const csrfToken = await this.authService.getCsrfToken();
      const sessionCookie = await this.authService.getSessionCookie();

      const response = await axios.get(`${this.baseUrl}/api/ai-conversation/user/${user.uid}`, {
        headers: {
          'X-CSRF-Token': csrfToken,
          Cookie: sessionCookie,
        },
        timeout: 10000,
      });

      return response.data.conversations || [];
    } catch (error) {
      console.error('❌ Error fetching user conversations:', error.message);
      return [];
    }
  }

  /**
   * Delete a conversation
   * @param {number} conversationId - The conversation node ID
   * @returns {Promise<boolean>} Success status
   */
  async deleteConversation(conversationId) {
    try {
      const csrfToken = await this.authService.getCsrfToken();
      const sessionCookie = await this.authService.getSessionCookie();

      await axios.delete(`${this.baseUrl}/api/ai-conversation/${conversationId}`, {
        headers: {
          'X-CSRF-Token': csrfToken,
          Cookie: sessionCookie,
        },
        timeout: 10000,
      });

      console.log('✅ Deleted conversation:', conversationId);
      return true;
    } catch (error) {
      console.error('❌ Error deleting conversation:', error.message);
      return false;
    }
  }

  /**
   * Get conversation statistics
   * @param {number} conversationId - The conversation node ID
   * @returns {Promise<Object>} Stats object
   */
  async getConversationStats(conversationId) {
    try {
      const csrfToken = await this.authService.getCsrfToken();
      const sessionCookie = await this.authService.getSessionCookie();

      const response = await axios.get(
        `${this.baseUrl}/api/ai-conversation/${conversationId}/stats`,
        {
          headers: {
            'X-CSRF-Token': csrfToken,
            Cookie: sessionCookie,
          },
          timeout: 10000,
        }
      );

      return response.data;
    } catch (error) {
      console.error('❌ Error fetching conversation stats:', error.message);
      return null;
    }
  }
}

// Export singleton - but use lazy initialization via getInstance()
const aiConversationService = AIConversationService.getInstance();

export default aiConversationService;
export { AIConversationService, aiConversationService };
