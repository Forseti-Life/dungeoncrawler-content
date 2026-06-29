/**
 * Chat Screen - Talk with Forseti
 *
 * AI-powered conversation interface for safety questions,
 * personalized advice, and community suggestions
 */

import React, { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  FlatList,
  StyleSheet,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Alert,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import AIConversationService from '../../services/AIConversationService';
import DrupalAuthService from '../../services/DrupalAuthService';
import { Colors } from '../../utils/colors';

const ChatScreen = ({ navigation, route }) => {
  const [messages, setMessages] = useState([]);
  const [inputText, setInputText] = useState('');
  const [loading, setLoading] = useState(false);
  const [conversationId, setConversationId] = useState(route?.params?.conversationId || null);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const flatListRef = useRef(null);

  useEffect(() => {
    checkAuthentication();
  }, []);

  useEffect(() => {
    if (conversationId && isAuthenticated) {
      loadConversationHistory();
    } else if (isAuthenticated && !conversationId) {
      startNewConversation();
    }
  }, [conversationId, isAuthenticated]);

  /**
   * Check if user is authenticated
   */
  const checkAuthentication = async () => {
    try {
      const user = await DrupalAuthService.getCurrentUser();
      if (user) {
        setIsAuthenticated(true);
      } else {
        // Redirect to login
        Alert.alert('Authentication Required', 'Please sign in to talk with Forseti', [
          { text: 'Cancel', onPress: () => navigation.goBack() },
          { text: 'Sign In', onPress: () => navigation.navigate('Profile') },
        ]);
      }
    } catch (error) {
      console.error('Error checking authentication:', error);
      setIsAuthenticated(false);
    }
  };

  /**
   * Start a new conversation
   */
  const startNewConversation = async () => {
    try {
      setLoading(true);
      const result = await AIConversationService.createConversation();
      setConversationId(result.conversation_id);

      // Add welcome message
      setMessages([
        {
          id: 'welcome',
          role: 'assistant',
          content:
            "Hello! I'm Forseti, your AI guardian for community safety. I can help you with:\n\n• Safety questions about your neighborhood\n• Personalized crime insights\n• Understanding safety data\n• Making suggestions for improvements\n\nWhat would you like to know?",
          timestamp: new Date().toISOString(),
        },
      ]);
    } catch (error) {
      Alert.alert('Error', 'Failed to start conversation. Please try again.');
      console.error('Error starting conversation:', error);
    } finally {
      setLoading(false);
    }
  };

  /**
   * Load conversation history
   */
  const loadConversationHistory = async () => {
    try {
      setLoading(true);
      const history = await AIConversationService.getConversationHistory(conversationId);
      setMessages(
        history.map((msg, index) => ({
          id: `msg-${index}`,
          role: msg.role,
          content: msg.content,
          timestamp: msg.timestamp,
        }))
      );
    } catch (error) {
      console.error('Error loading history:', error);
    } finally {
      setLoading(false);
    }
  };

  /**
   * Send a message
   */
  const sendMessage = async () => {
    if (!inputText.trim() || loading) return;

    const userMessage = {
      id: `user-${Date.now()}`,
      role: 'user',
      content: inputText.trim(),
      timestamp: new Date().toISOString(),
    };

    // Add user message to UI
    setMessages(prev => [...prev, userMessage]);
    setInputText('');
    setLoading(true);

    try {
      // Send to API
      const response = await AIConversationService.sendMessage(conversationId, userMessage.content);

      // Add AI response to UI
      const aiMessage = {
        id: `ai-${Date.now()}`,
        role: 'assistant',
        content: response.response,
        timestamp: new Date().toISOString(),
        suggestion_created: response.suggestion_created || false,
      };

      setMessages(prev => [...prev, aiMessage]);

      // If a suggestion was created, show confirmation
      if (response.suggestion_created) {
        Alert.alert(
          'Suggestion Submitted',
          'Your suggestion has been recorded and will be reviewed by our team. Thank you for helping make our community safer!',
          [{ text: 'OK' }]
        );
      }
    } catch (error) {
      Alert.alert('Error', 'Failed to send message. Please try again.');
      console.error('Error sending message:', error);

      // Remove user message on error
      setMessages(prev => prev.filter(msg => msg.id !== userMessage.id));
    } finally {
      setLoading(false);
    }
  };

  /**
   * Render a message bubble
   */
  const renderMessage = ({ item }) => {
    const isUser = item.role === 'user';

    return (
      <View style={[styles.messageBubble, isUser ? styles.userBubble : styles.aiBubble]}>
        {!isUser && (
          <Icon name="shield-check" size={20} color={Colors.cyan} style={styles.aiIcon} />
        )}
        <View style={[styles.messageContent, isUser ? styles.userContent : styles.aiContent]}>
          <Text style={[styles.messageText, isUser ? styles.userText : styles.aiText]}>
            {item.content}
          </Text>
          {item.suggestion_created && (
            <View style={styles.suggestionBadge}>
              <Icon name="check-circle" size={14} color={Colors.success} />
              <Text style={styles.suggestionText}>Suggestion created</Text>
            </View>
          )}
        </View>
      </View>
    );
  };

  /**
   * Scroll to bottom
   */
  const scrollToBottom = () => {
    if (flatListRef.current && messages.length > 0) {
      flatListRef.current.scrollToEnd({ animated: true });
    }
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  if (!isAuthenticated) {
    return (
      <View style={styles.container}>
        <ActivityIndicator size="large" color={Colors.cyan} />
      </View>
    );
  }

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      keyboardVerticalOffset={Platform.OS === 'ios' ? 90 : 0}
    >
      {/* Header */}
      <View style={styles.header}>
        <Icon name="robot" size={24} color={Colors.cyan} />
        <Text style={styles.headerTitle}>Talk with Forseti</Text>
        <TouchableOpacity onPress={() => navigation.goBack()}>
          <Icon name="close" size={24} color={Colors.text} />
        </TouchableOpacity>
      </View>

      {/* Messages */}
      <FlatList
        ref={flatListRef}
        data={messages}
        renderItem={renderMessage}
        keyExtractor={item => item.id}
        contentContainerStyle={styles.messageList}
        onContentSizeChange={scrollToBottom}
        ListEmptyComponent={
          <View style={styles.emptyContainer}>
            <Icon name="chat-outline" size={64} color={Colors.textSecondary} />
            <Text style={styles.emptyText}>Start a conversation with Forseti</Text>
          </View>
        }
      />

      {/* Loading indicator */}
      {loading && (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="small" color={Colors.cyan} />
          <Text style={styles.loadingText}>Forseti is thinking...</Text>
        </View>
      )}

      {/* Input */}
      <View style={styles.inputContainer}>
        <TextInput
          style={styles.input}
          placeholder="Ask Forseti anything about safety..."
          placeholderTextColor={Colors.textSecondary}
          value={inputText}
          onChangeText={setInputText}
          multiline
          maxLength={1000}
        />
        <TouchableOpacity
          style={[styles.sendButton, (!inputText.trim() || loading) && styles.sendButtonDisabled]}
          onPress={sendMessage}
          disabled={!inputText.trim() || loading}
        >
          <Icon
            name="send"
            size={24}
            color={!inputText.trim() || loading ? Colors.textSecondary : Colors.background}
          />
        </TouchableOpacity>
      </View>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  aiBubble: {
    alignSelf: 'flex-start',
  },
  aiContent: {
    backgroundColor: Colors.card,
    borderColor: Colors.border,
    borderWidth: 1,
  },
  aiIcon: {
    marginRight: 8,
    marginTop: 4,
  },
  aiText: {
    color: Colors.text,
  },
  container: {
    backgroundColor: Colors.background,
    flex: 1,
  },
  emptyContainer: {
    alignItems: 'center',
    flex: 1,
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyText: {
    color: Colors.textSecondary,
    fontSize: 16,
    marginTop: 16,
  },
  header: {
    alignItems: 'center',
    backgroundColor: Colors.card,
    borderBottomColor: Colors.border,
    borderBottomWidth: 1,
    flexDirection: 'row',
    justifyContent: 'space-between',
    padding: 16,
  },
  headerTitle: {
    color: Colors.text,
    flex: 1,
    fontSize: 18,
    fontWeight: '600',
    marginLeft: 12,
  },
  input: {
    backgroundColor: Colors.background,
    borderRadius: 20,
    color: Colors.text,
    flex: 1,
    fontSize: 15,
    marginRight: 8,
    maxHeight: 100,
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  inputContainer: {
    alignItems: 'flex-end',
    backgroundColor: Colors.card,
    borderTopColor: Colors.border,
    borderTopWidth: 1,
    flexDirection: 'row',
    padding: 12,
  },
  loadingContainer: {
    alignItems: 'center',
    backgroundColor: Colors.card,
    borderTopColor: Colors.border,
    borderTopWidth: 1,
    flexDirection: 'row',
    justifyContent: 'center',
    padding: 12,
  },
  loadingText: {
    color: Colors.textSecondary,
    fontSize: 14,
    marginLeft: 8,
  },
  messageBubble: {
    flexDirection: 'row',
    marginBottom: 16,
    maxWidth: '85%',
  },
  messageContent: {
    borderRadius: 16,
    flex: 1,
    padding: 12,
  },
  messageList: {
    flexGrow: 1,
    padding: 16,
  },
  messageText: {
    fontSize: 15,
    lineHeight: 20,
  },
  sendButton: {
    alignItems: 'center',
    backgroundColor: Colors.cyan,
    borderRadius: 22,
    height: 44,
    justifyContent: 'center',
    width: 44,
  },
  sendButtonDisabled: {
    backgroundColor: Colors.border,
  },
  suggestionBadge: {
    alignItems: 'center',
    borderTopColor: Colors.border,
    borderTopWidth: 1,
    flexDirection: 'row',
    marginTop: 8,
    paddingTop: 8,
  },
  suggestionText: {
    color: Colors.success,
    fontSize: 12,
    marginLeft: 4,
  },
  userBubble: {
    alignSelf: 'flex-end',
    flexDirection: 'row-reverse',
  },
  userContent: {
    backgroundColor: Colors.cyan,
  },
  userText: {
    color: Colors.background,
  },
});

export default ChatScreen;
