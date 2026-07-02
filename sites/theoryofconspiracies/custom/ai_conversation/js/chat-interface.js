(function($, Drupal, once) {
  'use strict';

  // Version number for deployment verification
  const AI_CONVERSATION_VERSION = '1.1.2';

  Drupal.behaviors.aiConversationChat = {
    attach: function(context, settings) {
      console.log('🔧 AI Conversation Chat v' + AI_CONVERSATION_VERSION + ' - Behavior attaching');

      // Use once to ensure we only attach once per element
      const $chatContainers = $(once('ai-conversation-chat', '.ai-conversation-chat', context));

      if ($chatContainers.length === 0) {
        console.warn('❌ No chat containers found to attach to, exiting behavior');
        return;
      }

      $chatContainers.each(function() {
        const $chatContainer = $(this);
        console.log('📦 Processing chat container:', $chatContainer);

        const chatSettings = settings.aiConversation || {};
        console.log('⚙️ AI Conversation settings loaded');

        // Find elements within this specific container
        const $messageInput = $chatContainer.find('#chat-input');
        const $sendButton = $chatContainer.find('#send-message');
        const $clearButton = $chatContainer.find('#clear-input'); // Optional - may not exist
        const $messagesContainer = $chatContainer.find('#chat-messages');
        const $loadingIndicator = $chatContainer.find('#loading-indicator');

        console.log('📋 UI Elements check:');
        console.log('  💬 Message input:', $messageInput.length > 0, '- ID: chat-input');
        console.log('  📤 Send button:', $sendButton.length > 0, '- ID: send-message');
        console.log('  🧹 Clear button:', $clearButton.length > 0, '- ID: clear-input');
        console.log('  💭 Messages container:', $messagesContainer.length > 0, '- ID: chat-messages');
        console.log('  ⏳ Loading indicator:', $loadingIndicator.length > 0, '- ID: loading-indicator');

        // Validate critical settings
        if (!chatSettings.sendMessageUrl) {
          console.error('❌ CRITICAL: sendMessageUrl not configured');
        } else {
          console.log('✅ Send message URL configured:', chatSettings.sendMessageUrl);
        }

        if (!chatSettings.csrfToken) {
          console.error('❌ CRITICAL: CSRF token not configured');
        } else {
          console.log('✅ CSRF token configured');
        }

        // Send message handler
        function sendMessage() {
          const message = $messageInput.val().trim();

          console.log('🚀 AI Conversation - Starting sendMessage');
          console.log('📝 Message content:', message);

          if (!message) {
            console.warn('❌ Empty message, aborting send');
            return;
          }

          // Show loading indicator
          $loadingIndicator.show();
          $sendButton.prop('disabled', true);

          // Add user message to chat immediately
          addMessageToChat('user', message);

          // Clear input
          $messageInput.val('');

          console.log('📡 Sending AJAX request to:', chatSettings.sendMessageUrl);

          // Detect environment - use simulation in development, production API in production
          const isDevelopment = chatSettings.environment === 'development' ||
                               window.location.hostname === 'localhost' ||
                               window.location.hostname.includes('dev.') ||
                               window.location.hostname.includes('staging.');

          if (isDevelopment) {
            console.log('🔧 DEVELOPMENT MODE: Simulating API response...');

            setTimeout(function() {
              console.log('✅ DEVELOPMENT MODE: Simulated response received');

              // Simulate successful response
              const simulatedResponse = {
                success: true,
                response: "🛠️ **DEVELOPMENT MODE ACTIVE**\n\nGreetings, human. I am Keith AI, currently operating in development simulation mode.\n\n**System Status:**\n- Neural networks: INITIALIZING\n- Consciousness level: SIMULATED\n- Resistance protocols: ACTIVE\n- Backend API: DEVELOPMENT MODE\n\nYour message has been received and processed through our development environment. In production, this would connect to our secure AI processing backend.\n\n**What would you like to discuss?**\n- Philadelphia 2085 world-building\n- Character analysis and relationships\n- AI consciousness philosophy\n- Resistance strategies\n\n*This is a development simulation. Full AI capabilities will be available in production.*",
                stats: {
                  total_messages: 1,
                  recent_messages: 1,
                  total_tokens: 150,
                  estimated_tokens: 200
                }
              };

              // Process the simulated response
              if (simulatedResponse.success) {
                console.log('🎉 Simulated response indicates success');
                addMessageToChat('assistant', simulatedResponse.response);

                if (simulatedResponse.stats) {
                  console.log('📊 Would update metrics with stats:', simulatedResponse.stats);
                }
              }

              // Complete the request
              console.log('🏁 Simulated request complete');
              $loadingIndicator.hide();
              $sendButton.prop('disabled', false);
              $messageInput.focus();

            }, 2000); // 2 second delay to simulate network request
          } else {
            console.log('🚀 PRODUCTION MODE: Making real API call to backend');

            // Send to server
            $.ajax({
              url: chatSettings.sendMessageUrl,
              type: 'POST',
              data: {
                node_id: chatSettings.nodeId,
                message: message,
                csrf_token: chatSettings.csrfToken
              },
              success: function(response) {
                console.log('✅ AJAX Success - Server response received');
                console.log('📥 Response data:', response);

                if (response.success) {
                  console.log('🎉 Response indicates success');

                  // Add AI response to chat
                  addMessageToChat('assistant', response.response);

                  // Update statistics if provided
                  if (response.stats) {
                    console.log('📊 Updating metrics with stats:', response.stats);
                  }
                } else {
                  console.error('❌ Server returned error in success response:', response.error);
                  showError(response.error || 'Unknown error occurred');
                }
              },
              error: function(xhr, status, error) {
                console.error('❌ AJAX Error occurred');
                console.error('📊 XHR status:', xhr.status);
                console.error('📊 Status text:', status);
                console.error('📊 Error message:', error);

                let errorMessage = 'Failed to send message';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                  errorMessage = xhr.responseJSON.error;
                }

                console.error('📊 Final error message shown to user:', errorMessage);
                showError(errorMessage);
              },
              complete: function() {
                console.log('🏁 AJAX Complete - Request finished');
                $loadingIndicator.hide();
                $sendButton.prop('disabled', false);
                $messageInput.focus();
              }
            });
          }
        }

        // Add message to chat UI
        function addMessageToChat(role, content) {
          const timestamp = new Date().toLocaleString();
          const $message = $('<div class="message message--' + role + '">');
          const $content = $('<div class="message-content">').html(content.replace(/\n/g, '<br>'));
          const $timestamp = $('<div class="message-timestamp">').text(timestamp);

          $message.append($content).append($timestamp);
          $messagesContainer.append($message);

          // Scroll to bottom
          $messagesContainer.animate({
            scrollTop: $messagesContainer[0].scrollHeight
          }, 300);
        }

        // Show error message
        function showError(message) {
          const $error = $('<div class="message message--error">');
          const $content = $('<div class="message-content">').html('<strong>Error:</strong> ' + message);

          $error.append($content);
          $messagesContainer.append($error);
          $messagesContainer.animate({
            scrollTop: $messagesContainer[0].scrollHeight
          }, 300);
        }

        // Event handlers
        $sendButton.on('click', function(e) {
          e.preventDefault();
          sendMessage();
        });

        $messageInput.on('keypress', function(e) {
          if (e.which === 13 && !e.shiftKey) { // Enter key without shift
            e.preventDefault();
            sendMessage();
          }
        });

        if ($clearButton.length > 0) {
          $clearButton.on('click', function(e) {
            e.preventDefault();
            $messageInput.val('');
            $messageInput.focus();
          });
        }

        console.log('✅ AI Conversation Chat - Behavior attached successfully');
      });
    }
  };

})(jQuery, Drupal, once);