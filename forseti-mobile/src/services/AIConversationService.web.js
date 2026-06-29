/**
 * AI Conversation Service for Web (Mock)
 *
 * Web-compatible mock with simulated AI responses
 */

import DrupalAuthService from './DrupalAuthService';

// Mock conversation storage
const mockConversations = new Map();
let nextConversationId = 1;
let nextMessageId = 1;

class AIConversationService {
  constructor() {
    this.baseUrl = 'https://forseti.life';
    this.authService = DrupalAuthService.getInstance();
  }

  /**
   * Create a new AI conversation (mock)
   */
  async createConversation() {
    try {
      console.log('[AIConversationService.web] Creating mock conversation');

      const user = await this.authService.getCurrentUser();

      if (!user) {
        throw new Error('User must be authenticated to create conversation');
      }

      const conversationId = nextConversationId++;
      const conversation = {
        conversation_id: conversationId,
        title: `Conversation with Forseti - ${new Date().toLocaleString()}`,
        ai_model: 'claude-3-5-sonnet-20241022',
        created: Date.now(),
        messages: [],
      };

      mockConversations.set(conversationId, conversation);

      console.log('✅ Created mock AI conversation:', conversationId);
      return conversation;
    } catch (error) {
      console.error('❌ Error creating conversation:', error.message);
      throw error;
    }
  }

  /**
   * Send a message in a conversation (mock with simulated AI response)
   */
  async sendMessage(conversationId, message) {
    try {
      console.log(`📤 Sending mock message to conversation ${conversationId}`);

      const conversation = mockConversations.get(conversationId);
      if (!conversation) {
        throw new Error(`Conversation ${conversationId} not found`);
      }

      // Add user message
      const userMessage = {
        id: nextMessageId++,
        role: 'user',
        content: message.trim(),
        timestamp: Date.now(),
      };
      conversation.messages.push(userMessage);

      // Simulate AI thinking delay
      await new Promise(resolve => setTimeout(resolve, 1000));

      // Generate mock AI response
      const aiResponse = this.generateMockResponse(message);
      const aiMessage = {
        id: nextMessageId++,
        role: 'assistant',
        content: aiResponse,
        timestamp: Date.now(),
      };
      conversation.messages.push(aiMessage);

      console.log('✅ Received mock AI response');
      return {
        message: aiMessage,
        conversation_id: conversationId,
      };
    } catch (error) {
      console.error('❌ Error sending message:', error.message);
      throw error;
    }
  }

  /**
   * Generate mock AI response based on user message
   */
  generateMockResponse(userMessage) {
    const lower = userMessage.toLowerCase();

    if (lower.includes('safe') || lower.includes('safety') || lower.includes('crime')) {
      return "I'm Forseti, your personal safety assistant. Based on the current data, your area has a safety score of 72/100. There have been 15 incidents reported in the past 24 hours within a 1-mile radius. The safest hours are typically 9am-5pm. Would you like more detailed information about specific crime types or safety recommendations?";
    }

    if (lower.includes('location') || lower.includes('where')) {
      return "You're currently in San Francisco (mock location for web preview). I'm monitoring the safety conditions in your area and can provide real-time crime data and safety recommendations. Would you like me to analyze the safety of your current location?";
    }

    if (lower.includes('help') || lower.includes('what can you')) {
      return 'I can help you with:\n\n• Real-time safety scores for your location\n• Crime incident reports and analysis\n• Neighborhood safety comparisons\n• Safety recommendations based on time and location\n• Emergency contact assistance\n• Safe route suggestions\n\nWhat would you like to know?';
    }

    if (lower.includes('hello') || lower.includes('hi') || lower.includes('hey')) {
      return "Hello! I'm Forseti, your AI-powered safety companion. I'm here to help keep you informed about safety conditions in your area. How can I assist you today?";
    }

    if (lower.includes('thank')) {
      return "You're welcome! Stay safe out there. Feel free to ask me anything about your safety and surroundings anytime.";
    }

    // Default response
    return `I understand you're asking about "${userMessage}". This is a mock response for web preview. In the full app, I would provide detailed safety information and personalized recommendations. Is there anything specific about safety or crime data you'd like to know?`;
  }

  /**
   * Get conversation history (mock)
   */
  async getConversationHistory(conversationId) {
    try {
      console.log(`📜 Getting mock conversation history for ${conversationId}`);

      const conversation = mockConversations.get(conversationId);
      if (!conversation) {
        throw new Error(`Conversation ${conversationId} not found`);
      }

      return conversation.messages;
    } catch (error) {
      console.error('❌ Error getting conversation history:', error.message);
      throw error;
    }
  }

  /**
   * Get all user conversations (mock)
   */
  async getUserConversations() {
    try {
      console.log('[AIConversationService.web] Getting mock user conversations');

      const user = await this.authService.getCurrentUser();
      if (!user) {
        throw new Error('User must be authenticated');
      }

      const conversations = Array.from(mockConversations.values()).map(conv => ({
        id: conv.conversation_id,
        title: conv.title,
        created: conv.created,
        messageCount: conv.messages.length,
        lastMessage:
          conv.messages.length > 0 ? conv.messages[conv.messages.length - 1].content : null,
      }));

      return conversations;
    } catch (error) {
      console.error('❌ Error getting conversations:', error.message);
      throw error;
    }
  }

  /**
   * Delete a conversation (mock)
   */
  async deleteConversation(conversationId) {
    try {
      console.log(`🗑️ Deleting mock conversation ${conversationId}`);

      if (!mockConversations.has(conversationId)) {
        throw new Error(`Conversation ${conversationId} not found`);
      }

      mockConversations.delete(conversationId);
      return { success: true };
    } catch (error) {
      console.error('❌ Error deleting conversation:', error.message);
      throw error;
    }
  }

  /**
   * Update conversation title (mock)
   */
  async updateConversationTitle(conversationId, newTitle) {
    try {
      console.log(`📝 Updating conversation ${conversationId} title`);

      const conversation = mockConversations.get(conversationId);
      if (!conversation) {
        throw new Error(`Conversation ${conversationId} not found`);
      }

      conversation.title = newTitle;
      return { success: true };
    } catch (error) {
      console.error('❌ Error updating conversation title:', error.message);
      throw error;
    }
  }
}

// Export class as default
export default AIConversationService;
