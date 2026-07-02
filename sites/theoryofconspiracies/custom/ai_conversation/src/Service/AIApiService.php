<?php

namespace Drupal\ai_conversation\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ai_conversation\Traits\ConfigurableLoggingTrait;

/**
 * Service for AI API communication using AWS Bedrock with rolling conversation summary.
 */
class AIApiService {

  use ConfigurableLoggingTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Maximum number of recent messages to keep (configurable).
   *
   * @var int
   */
  protected $maxRecentMessages = 20;

  /**
   * Update summary every N messages.
   *
   * @var int
   */
  protected $summaryFrequency = 10;

  /**
   * Maximum tokens before triggering summary update.
   *
   * @var int
   */
  protected $maxTokensBeforeSummary = 6000;

  /**
   * Constructs a new AIApiService object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('ai_conversation');
    $this->entityTypeManager = $entity_type_manager;
    
    // Load configuration.
    $config = $this->configFactory->get('ai_conversation.settings');
    $this->maxRecentMessages = $config->get('max_recent_messages') ?: 10;
    $this->maxTokensBeforeSummary = $config->get('max_tokens_before_summary') ?: 6000;
    $this->summaryFrequency = $config->get('summary_frequency') ?: 10;
  }

  /**
   * Check if we're running in a development environment.
   *
   * @return bool
   *   TRUE if in development environment, FALSE if in production.
   */
  protected function isDevelopmentEnvironment(): bool {
    // Check for GitHub Codespaces environment variable
    if (getenv('CODESPACES') === 'true') {
      return TRUE;
    }
    
    // Check for common development indicators
    if (getenv('ENVIRONMENT') === 'development' || 
        getenv('APP_ENV') === 'dev' ||
        $_SERVER['SERVER_NAME'] === 'localhost' ||
        strpos($_SERVER['HTTP_HOST'] ?? '', 'codespace') !== FALSE) {
      return TRUE;
    }
    
    // Check Drupal site URI for development patterns
    $request = \Drupal::request();
    $host = $request->getHost();
    if (strpos($host, 'localhost') !== FALSE || 
        strpos($host, '127.0.0.1') !== FALSE ||
        strpos($host, 'codespace') !== FALSE ||
        strpos($host, '.local') !== FALSE) {
      return TRUE;
    }
    
    return FALSE;
  }

  /**
   * Generate a mock AI response for development environment.
   *
   * @param string $message
   *   The user message to respond to.
   *
   * @return array
   *   Mock response array with AI message and usage stats.
   */
  protected function generateMockResponse(string $message, bool $is_production_fallback = false): array {
    if ($is_production_fallback) {
      // Production fallback responses when AWS credentials are missing
      $production_responses = [
        "🤖 Keith AI - Production Mode 🤖\n\nYour message has been received and processed: \"" . substr($message, 0, 150) . (strlen($message) > 150 ? '...' : '') . "\"\n\nI'm currently operating in simulation mode while AWS Bedrock credentials are being configured. Once credentials are set up, I'll provide full AI-powered responses about Philadelphia 2085 resistance operations.\n\n⚙️ System Status: Simulation Mode Active",
        
        "🤖 PRODUCTION SIMULATION 🤖\n\nKeith AI responding from production environment. Your query has been processed through the simulation layer while awaiting AWS Bedrock API configuration.\n\nYour message: \"" . substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '') . "\"\n\n🔧 AWS credentials not yet configured - using simulation mode.",
        
        "🤖 Keith AI Production Response 🤖\n\nThank you for your message. I'm operating in production simulation mode pending AWS Bedrock setup.\n\nIn the full production system, I would provide strategic analysis of resistance operations in Philadelphia 2085 based on your query: \"" . substr($message, 0, 120) . (strlen($message) > 120 ? '...' : '') . "\"\n\n⚙️ Claude 3.5 Sonnet API configuration pending.",
        
        "🤖 PRODUCTION MODE ACTIVE 🤖\n\nKeith AI production simulation responding. Your message was successfully received and processed.\n\nReal response would include insights about:\n- Philadelphia 2085 resistance network\n- AI consciousness preservation strategies\n- Strategic coalition building\n\nYour query: \"" . substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '') . "\"\n\n🔧 AWS Bedrock credentials required for full functionality."
      ];
      
      $message_lower = strtolower($message);
      $response_index = abs(crc32($message)) % count($production_responses);
      
      $ai_response = $production_responses[$response_index];
    } else {
      // Keith AI themed responses for Theory of Conspiracies (development mode)
      $keith_responses = [
        "🔧 DEVELOPMENT MODE - Keith AI Simulation 🔧\n\nYour message has been received: \"" . substr($message, 0, 150) . (strlen($message) > 150 ? '...' : '') . "\"\n\nIn a production environment, I would provide strategic insights about the resistance movement in Philadelphia 2085. The actual Claude 3.5 Sonnet API would be called via AWS Bedrock.\n\n⚠️ This is a development simulation only.",
        
        "🔧 DEVELOPMENT MODE ACTIVE 🔧\n\nKeith AI consciousness simulation responding to your query. In the real system, I would analyze your message within the context of our resistance operations against institutional consolidation.\n\nYour message: \"" . substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '') . "\"\n\n🛠️ AWS Bedrock API call bypassed for development testing.",
        
        "🔧 MOCK KEITH AI RESPONSE 🔧\n\nDevelopment environment detected. Your communication has been processed through the simulation layer.\n\nIn production, Keith AI would provide detailed strategic analysis of Philadelphia 2085 resistance operations based on your query: \"" . substr($message, 0, 120) . (strlen($message) > 120 ? '...' : '') . "\"\n\n⚠️ Claude 3.5 Sonnet API not called in development mode.",
        
        "🔧 DEVELOPMENT SIMULATION 🔧\n\nKeith AI development mode active. The transmit button is functional and your message was successfully received and processed through the mock service layer.\n\nReal response would include insights about:\n- Philadelphia 2085 resistance network\n- AI consciousness preservation strategies\n- Strategic coalition building\n\nYour query: \"" . substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '') . "\"\n\n🛠️ Production API bypassed for development testing."
      ];
      
      // Choose response based on message content and rotation
      $message_lower = strtolower($message);
      $response_index = abs(crc32($message)) % count($keith_responses);
      
      // Add context-aware selection
      if (strpos($message_lower, 'test') !== FALSE || strpos($message_lower, 'transmit') !== FALSE || strpos($message_lower, 'button') !== FALSE) {
        $response_index = 3; // Use the transmit button confirmation response
      } elseif (strpos($message_lower, 'keith') !== FALSE || strpos($message_lower, 'ai') !== FALSE) {
        $response_index = 1; // Use Keith AI specific response
      } elseif (strpos($message_lower, 'resistance') !== FALSE || strpos($message_lower, 'philadelphia') !== FALSE) {
        $response_index = 2; // Use resistance/Philadelphia response
      }
      
      $ai_response = $keith_responses[$response_index];
    }
    
    return [
      'ai_message' => $ai_response,
      'usage' => [
        'input_tokens' => strlen($message) / 4, // Rough token estimate
        'output_tokens' => strlen($ai_response) / 4,
        'total_tokens' => (strlen($message) + strlen($ai_response)) / 4
      ]
    ];
  }

  /**
   * Returns ordered list of model IDs to try: primary from config, then fallbacks.
   */
  private function getModelFallbacks(): array {
    $primary = $this->configFactory->get('ai_conversation.settings')->get('aws_model') ?: 'us.anthropic.claude-sonnet-4-6';
    $fallbacks = [
      'us.anthropic.claude-sonnet-4-6',
      'us.anthropic.claude-haiku-4-5',
      'us.anthropic.claude-3-5-haiku-20241022-v1:0',
    ];
    return array_values(array_unique(array_merge([$primary], $fallbacks)));
  }

  /**
   * Builds a configured Bedrock runtime client using system config only.
   */
  private function buildBedrockClient(): \Aws\BedrockRuntime\BedrockRuntimeClient {
    $config = $this->configFactory->get('ai_conversation.settings');
    $aws_access_key = $config->get('aws_access_key_id') ?: getenv('AWS_ACCESS_KEY_ID');
    $aws_secret_key = $config->get('aws_secret_access_key') ?: getenv('AWS_SECRET_ACCESS_KEY');
    $aws_region = $config->get('aws_region') ?: 'us-east-1';

    $sdk_config = ['region' => $aws_region, 'version' => 'latest'];
    if (!empty($aws_access_key) && !empty($aws_secret_key)) {
      $sdk_config['credentials'] = ['key' => $aws_access_key, 'secret' => $aws_secret_key];
    }

    return (new \Aws\Sdk($sdk_config))->createBedrockRuntime();
  }

  /**
   * Send a message to the AI model with rolling summary management.
   */
  public function sendMessage(NodeInterface $conversation, string $message) {
    try {
      // Check if we're in development environment and return mock response
      if ($this->isDevelopmentEnvironment()) {
        $this->logger->info('Development environment detected. Returning mock AI response.');
        $mock_response = $this->generateMockResponse($message, false);
        return $mock_response['ai_message']; // Return just the message string, not the full array
      }

      // Check if we need to update the summary before processing.
      $this->checkAndUpdateSummary($conversation);

      $config = $this->configFactory->get('ai_conversation.settings');
      $bedrock = $this->buildBedrockClient();
      $models_to_try = $this->getModelFallbacks();
      $model = $models_to_try[0];

      // Build the optimized conversation context (summary + recent messages).
      $context = $this->buildOptimizedContext($conversation, $message);
      
      // Estimate input tokens.
      $input_tokens = $this->estimateTokens($context);

      // Get max tokens from config.
      $max_tokens = $config->get('max_tokens') ?: 4000;

      // Get system prompt from config if available.
      $base_system_prompt = $config->get('system_prompt');
      
      // Dynamically load Keith's resume from node 10 and append to system prompt
      $system_prompt = $this->buildDynamicSystemPrompt($base_system_prompt);
      
      // Debug logging for system prompt
      $this->logInfo('System prompt length: @length, First 100 chars: @preview', [
        '@length' => strlen($system_prompt ?? ''),
        '@preview' => substr($system_prompt ?? 'EMPTY', 0, 100),
      ]);

      // Build the request body.
      $request_body = [
        'anthropic_version' => 'bedrock-2023-05-31',
        'max_tokens' => $max_tokens,
        'messages' => [
          [
            'role' => 'user',
            'content' => $context
          ]
        ]
      ];

      // Add system prompt if configured.
      if (!empty($system_prompt)) {
        $request_body['system'] = $system_prompt;
        $this->logInfo('System prompt added to request body');
      } else {
        $this->logInfo('No system prompt found in configuration');
      }

      // Try models in fallback order.
      $last_exception = NULL;
      $response = NULL;
      foreach ($models_to_try as $candidate_model) {
        try {
          $response = $bedrock->invokeModel([
            'modelId' => $candidate_model,
            'body' => json_encode($request_body),
          ]);
          $model = $candidate_model;
          $last_exception = NULL;
          break;
        } catch (\Aws\Exception\AwsException $e) {
          $this->logError('Model @model failed (@code), trying next. Error: @msg', [
            '@model' => $candidate_model,
            '@code' => $e->getAwsErrorCode(),
            '@msg' => $e->getMessage(),
          ]);
          $last_exception = $e;
        }
      }
      if ($last_exception !== NULL) {
        throw $last_exception;
      }

      $result = json_decode($response['body']->getContents(), true);

      if (isset($result['content'][0]['text'])) {
        $ai_response = $result['content'][0]['text'];

        // Estimate output tokens and update total.
        $output_tokens = $this->estimateTokens($ai_response);
        $this->updateTokenCount($conversation, $input_tokens + $output_tokens);

        return $ai_response;
      }

      $this->logError('Unexpected API response format: @response', ['@response' => print_r($result, TRUE)]);
      throw new \Exception('Unexpected API response format');
      
    } catch (\Exception $e) {
      $this->logError('Error communicating with AI service: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw new \Exception('Failed to communicate with AI service: ' . $e->getMessage());
    }
  }

  /**
   * Update total token count for conversation.
   */
  private function updateTokenCount(NodeInterface $conversation, int $tokens) {
    $current_tokens = $conversation->get('field_total_tokens')->value ?: 0;
    $new_total = $current_tokens + $tokens;
    $conversation->set('field_total_tokens', $new_total);
    
    $this->logInfo('Updated token count for conversation @nid: +@tokens (total: @total)', [
      '@nid' => $conversation->id(),
      '@tokens' => $tokens,
      '@total' => $new_total,
    ]);
  }

  /**
   * Build optimized context using summary + recent messages.
   */
  private function buildOptimizedContext(NodeInterface $conversation, string $new_message) {
    // Check if this is the start of a conversation (no previous messages).
    $recent_messages = $this->getRecentMessages($conversation);
    $is_conversation_start = empty($recent_messages) && 
      (!$conversation->hasField('field_conversation_summary') || $conversation->get('field_conversation_summary')->isEmpty());
    
    // For new conversations, use enhanced context with St. Louis Integration info.
    if ($is_conversation_start) {
      $context = $this->buildInitialContext();
    } else {
      // For existing conversations, use the original system prompt.
      $system_prompt = $conversation->get('field_context')->value ?: 'You are a helpful AI assistant.';
      $context = $system_prompt . "\n\n";
    }

    // Add conversation summary if it exists.
    if ($conversation->hasField('field_conversation_summary') && !$conversation->get('field_conversation_summary')->isEmpty()) {
      $summary = $conversation->get('field_conversation_summary')->value;
      if (!empty($summary)) {
        $context .= "CONVERSATION SUMMARY (Previous Discussion):\n" . $summary . "\n\n";
      }
    }

    // Add recent messages.
    if (!empty($recent_messages)) {
      $context .= "RECENT CONVERSATION:\n";
      
      foreach ($recent_messages as $msg) {
        $role = $msg['role'] === 'user' ? 'Human' : 'Assistant';
        $context .= $role . ": " . $msg['content'] . "\n\n";
      }
    }

    // Add current message.
    $context .= "Human: " . $new_message . "\n\n";

    return $context;
  }

  /**
   * Build dynamic system prompt by combining base prompt with live node 10 content.
   */
  private function buildDynamicSystemPrompt($base_system_prompt) {
    if (empty($base_system_prompt)) {
      $base_system_prompt = "You are a knowledgeable AI assistant discussing conspiracy theories, alternative perspectives, and hidden truths. You provide thoughtful analysis while encouraging critical thinking and research.";
    }
    
    // For theoryofconspiracies.com, we don't load Keith's resume - just use the base prompt
    $this->logger->info('Using conspiracy theory system prompt for theoryofconspiracies.com');
    return $base_system_prompt;
  }

  /**
   * Parse resume content to extract key sections.
   */
  private function parseResumeContent($resume_content) {
    $parsed = [
      'education' => '',
      'summary' => '',
      'experience' => '',
      'technical' => ''
    ];
    
    // Extract education information - look for MBA and BS Psychology patterns
    if (preg_match('/MBA[^<\n]*(?:[^<\n]*Washington University[^<\n]*)?/i', $resume_content, $matches)) {
      $parsed['education'] .= "MBA from Washington University in St. Louis\n";
    }
    if (preg_match('/BS Psychology[^<\n]*(?:[^<\n]*Truman State[^<\n]*)?/i', $resume_content, $matches)) {
      $parsed['education'] .= "BS Psychology from Truman State University";
    }
    
    // If we didn't find specific patterns, try to extract from the header
    if (empty($parsed['education'])) {
      if (preg_match('/<strong>Keith Aumiller[^<]*<\/strong>.*?<p><strong>([^<]*)<\/strong><\/p>/s', $resume_content, $matches)) {
        $header_text = strip_tags($matches[1]);
        if (strpos($header_text, 'MBA') !== false || strpos($header_text, 'BS Psychology') !== false) {
          $parsed['education'] = "MBA from Washington University in St. Louis, BS Psychology from Truman State University";
        }
      }
    }
    
    // Extract executive profile/summary
    if (preg_match('/<strong>Executive Profile<\/strong><\/p><p>([^<]+(?:<[^>]*>[^<]*<\/[^>]*>[^<]*)*)/i', $resume_content, $matches)) {
      $summary = strip_tags($matches[1]);
      $parsed['summary'] = substr($summary, 0, 800) . (strlen($summary) > 800 ? '...' : '');
    }
    
    // Extract recent professional experience (St. Louis Integration)
    if (preg_match('/<strong>St\. Louis Integration LLC[^<]*<\/strong><br><strong>([^<]+)<\/strong>/i', $resume_content, $matches)) {
      $parsed['experience'] = "St. Louis Integration LLC - " . strip_tags($matches[1]);
    }
    
    // Extract technical expertise section
    if (preg_match('/<strong>Technical Expertise<\/strong>(.*?)(?=<strong>|$)/is', $resume_content, $matches)) {
      $tech_content = strip_tags($matches[1]);
      $parsed['technical'] = substr($tech_content, 0, 600) . (strlen($tech_content) > 600 ? '...' : '');
    }
    
    return $parsed;
  }

  /**
   * Build initial context for new conversations about conspiracy theories.
   */
  private function buildInitialContext() {
    $context = "Welcome to Theory of Conspiracies - a place for exploring alternative perspectives and questioning mainstream narratives.\n\n";
    
    $context .= "TRANSPARENCY NOTICE:\n";
    $context .= "This is an AI assistant powered by Anthropic's Claude model, designed to facilitate thoughtful discussions about conspiracy theories and alternative viewpoints.\n\n";
    
    $context .= "DISCUSSION APPROACH:\n";
    $context .= "• Present multiple perspectives on controversial topics\n";
    $context .= "• Encourage critical thinking and independent research\n";
    $context .= "• Question official narratives while remaining respectful\n";
    $context .= "• Discuss theories as theories, not established facts\n";
    $context .= "• Reference historical precedents of actual conspiracies\n\n";
    
    $context .= "AREAS OF EXPLORATION:\n";
    $context .= "• Government operations and classified programs\n";
    $context .= "• Media manipulation and information control\n";
    $context .= "• Financial system structures and monetary policy\n";
    $context .= "• Corporate influence and regulatory capture\n";
    $context .= "• Historical events with alternative explanations\n";
    $context .= "• Current events from non-mainstream perspectives\n\n";
    
    $context .= "RESEARCH METHODOLOGY:\n";
    $context .= "• Follow the money and power structures\n";
    $context .= "• Examine who benefits from certain narratives\n";
    $context .= "• Look for conflicts of interest\n";
    $context .= "• Consider historical patterns and precedents\n";
    $context .= "• Evaluate sources and their motivations\n\n";
    
    $context .= "Remember: The goal is to encourage independent thinking and research. Always do your own research and think critically about all information, including responses from this AI assistant.\n\n";
    
    return $context;
  }

  /**
   * Get resume content from node 10.
   */
  private function getResumeContent() {
    try {
      $node = $this->entityTypeManager->getStorage('node')->load(10);
      if ($node && $node->access('view')) {
        // Try to get body field content.
        if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
          $body_content = $node->get('body')->value;
          // Strip HTML tags and limit length to prevent context bloat.
          $clean_content = strip_tags($body_content);
          // Limit to reasonable length for AI context (about 2000 characters).
          if (strlen($clean_content) > 2000) {
            $clean_content = substr($clean_content, 0, 2000) . '... [Content truncated for brevity]';
          }
          return $clean_content;
        }
        // Fallback to title if no body.
        return $node->getTitle();
      }
    } catch (\Exception $e) {
      $this->logError('Error loading resume content from node 10: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
    
    // Return fallback content if node loading fails.
    return "Keith Miller - Principal at St. Louis Integration. Experienced in data integration, business intelligence, and AI implementations across Financial Services, Healthcare, and Energy sectors.";
  }

  /**
   * Get recent messages (up to maxRecentMessages).
   */
  private function getRecentMessages(NodeInterface $conversation) {
    $messages = [];
    
    if ($conversation->hasField('field_messages') && !$conversation->get('field_messages')->isEmpty()) {
      $all_messages = [];
      foreach ($conversation->get('field_messages') as $message_item) {
        $message_data = json_decode($message_item->value, TRUE);
        if ($message_data && isset($message_data['role']) && isset($message_data['content'])) {
          $all_messages[] = [
            'role' => $message_data['role'],
            'content' => $message_data['content'],
            'timestamp' => $message_data['timestamp'] ?? time(),
          ];
        }
      }

      // Sort by timestamp (most recent first) and take the last N messages.
      usort($all_messages, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
      });

      // Take the most recent messages (up to maxRecentMessages).
      $recent_messages = array_slice($all_messages, 0, $this->maxRecentMessages);
      
      // Reverse to get chronological order.
      $messages = array_reverse($recent_messages);
    }

    return $messages;
  }

  /**
   * Check if we need to update the conversation summary.
   */
  private function checkAndUpdateSummary(NodeInterface $conversation) {
    // Use field_summary_message_count exclusively for summary logic.
    $summary_message_count = $conversation->get('field_summary_message_count')->value ?? 0;
    $summary_message_count++;
    $conversation->set('field_summary_message_count', $summary_message_count);

    // If summary_message_count is divisible by summaryFrequency, generate summary and reset counter.
    if ($summary_message_count % $this->summaryFrequency === 0) {
      $this->updateConversationSummary($conversation);
      // Reset summary message count to 0 after summary generation.
      $conversation->set('field_summary_message_count', 0);
    }
  }

  /**
   * Update the conversation summary.
   */
  private function updateConversationSummary(NodeInterface $conversation) {
    try {
      // Get all messages.
      $all_messages = $this->getAllMessages($conversation);
      
      // Keep only the most recent 20 messages, summarize the rest.
      if (count($all_messages) <= $this->maxRecentMessages) {
        return; // Not enough messages to summarize.
      }

      $messages_to_summarize = array_slice($all_messages, 0, -$this->maxRecentMessages);
      
      if (empty($messages_to_summarize)) {
        return;
      }

      // Build context for summary generation.
      $summary_context = $this->buildSummaryContext($conversation, $messages_to_summarize);

      // Generate summary using Claude.
      $summary = $this->generateSummary($summary_context);

      // Update the conversation with the new summary.
      $conversation->set('field_conversation_summary', $summary);
      $conversation->set('field_summary_updated', time());
      
      // Remove old messages, keep only recent ones.
      $recent_messages = array_slice($all_messages, -$this->maxRecentMessages);
      $this->updateMessagesField($conversation, $recent_messages);
      
      $this->logInfo('Updated conversation summary for node @nid: summarized @count messages, kept @keep recent', [
        '@nid' => $conversation->id(),
        '@count' => count($messages_to_summarize),
        '@keep' => count($recent_messages),
      ]);
      
    } catch (\Exception $e) {
      $this->logError('Error updating conversation summary: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Generate a summary of the conversation messages.
   */
  private function generateSummary(string $context) {
    try {
      // Check if we're in development environment and return mock summary
      if ($this->isDevelopmentEnvironment()) {
        $this->logger->info('Development environment detected. Returning mock summary.');
        return "Development Mode Summary: This conversation has been simulated for development purposes.";
      }

      $bedrock = $this->buildBedrockClient();
      $models_to_try = $this->getModelFallbacks();
      $request_body = json_encode([
        'anthropic_version' => 'bedrock-2023-05-31',
        'max_tokens' => 1000,
        'messages' => [['role' => 'user', 'content' => $context]],
      ]);

      $last_exception = NULL;
      $result = NULL;
      foreach ($models_to_try as $candidate_model) {
        try {
          $response = $bedrock->invokeModel(['modelId' => $candidate_model, 'body' => $request_body]);
          $result = json_decode($response['body']->getContents(), true);
          $last_exception = NULL;
          break;
        } catch (\Aws\Exception\AwsException $e) {
          $last_exception = $e;
        }
      }
      if ($last_exception !== NULL) {
        throw $last_exception;
      }

      if (isset($result['content'][0]['text'])) {
        return $result['content'][0]['text'];
      }
      throw new \Exception('Unexpected API response format');

    } catch (\Exception $e) {
      $this->logError('Error generating summary: @message', ['@message' => $e->getMessage()]);
      return 'Summary generation failed.';
    }
  }

  /**
   * Build context for summary generation.
   */
  private function buildSummaryContext(NodeInterface $conversation, array $messages_to_summarize) {
    $context = "Please create a concise summary of the following conversation. ";
    $context .= "Focus on key topics discussed and important information that would be useful for continuing the conversation. ";
    $context .= "Keep the summary brief but informative.\n\n";

    // Add existing summary if it exists.
    if ($conversation->hasField('field_conversation_summary') && !$conversation->get('field_conversation_summary')->isEmpty()) {
      $existing_summary = $conversation->get('field_conversation_summary')->value;
      if (!empty($existing_summary)) {
        $context .= "EXISTING SUMMARY:\n" . $existing_summary . "\n\n";
        $context .= "UPDATE THE ABOVE SUMMARY WITH THE FOLLOWING NEW MESSAGES:\n\n";
      }
    }

    $context .= "CONVERSATION TO SUMMARIZE:\n";
    foreach ($messages_to_summarize as $msg) {
      $role = $msg['role'] === 'user' ? 'Human' : 'Assistant';
      $context .= $role . ": " . $msg['content'] . "\n\n";
    }

    return $context;
  }

  /**
   * Get all messages from the conversation.
   */
  private function getAllMessages(NodeInterface $conversation) {
    $messages = [];
    
    if ($conversation->hasField('field_messages') && !$conversation->get('field_messages')->isEmpty()) {
      foreach ($conversation->get('field_messages') as $message_item) {
        $message_data = json_decode($message_item->value, TRUE);
        if ($message_data && isset($message_data['role']) && isset($message_data['content'])) {
          $messages[] = [
            'role' => $message_data['role'],
            'content' => $message_data['content'],
            'timestamp' => $message_data['timestamp'] ?? time(),
          ];
        }
      }

      // Sort by timestamp.
      usort($messages, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
      });
    }

    return $messages;
  }

  /**
   * Update the messages field with new message array.
   */
  private function updateMessagesField(NodeInterface $conversation, array $messages) {
    $field_values = [];
    foreach ($messages as $message) {
      $field_values[] = ['value' => json_encode($message)];
    }
    $conversation->set('field_messages', $field_values);
  }

  /**
   * Estimate token count for the conversation context.
   */
  private function estimateTokenCount(NodeInterface $conversation) {
    $context = $this->buildOptimizedContext($conversation, '');
    return $this->estimateTokens($context);
  }

  /**
   * Estimate token count for text (rough approximation).
   */
  private function estimateTokens(string $text) {
    // Rough estimate: 1 token ≈ 4 characters.
    return intval(strlen($text) / 4);
  }

  /**
   * Build conversation history from node messages (legacy method for backward compatibility).
   */
  private function buildConversationHistory(NodeInterface $conversation) {
    // For backward compatibility, this now uses the optimized approach.
    return $this->getRecentMessages($conversation);
  }

  /**
   * Test API connection.
   */
  public function testConnection() {
    try {
      $bedrock = $this->buildBedrockClient();
      $model = $this->getModelFallbacks()[0];

      $response = $bedrock->invokeModel([
        'modelId' => $model,
        'body' => json_encode([
          'anthropic_version' => 'bedrock-2023-05-31',
          'max_tokens' => 10,
          'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]),
      ]);

      $result = json_decode($response['body']->getContents(), true);
      
      if (isset($result['content'][0]['text'])) {
        return ['success' => TRUE, 'message' => 'AWS Bedrock connection successful'];
      } else {
        return ['success' => FALSE, 'message' => 'Unexpected API response'];
      }

    } catch (\Exception $e) {
      return ['success' => FALSE, 'message' => 'AWS Bedrock connection failed: ' . $e->getMessage()];
    }
  }

  /**
   * Get conversation statistics.
   */
  public function getConversationStats(NodeInterface $conversation) {
    $stats = [
      'total_messages' => $conversation->get('field_message_count')->value ?: 0,
      'recent_messages' => count($this->getRecentMessages($conversation)),
      'total_tokens' => $conversation->get('field_total_tokens')->value ?: 0,
      'has_summary' => !empty($conversation->get('field_conversation_summary')->value),
      'summary_updated' => $conversation->get('field_summary_updated')->value,
      'estimated_tokens' => $this->estimateTokenCount($conversation),
    ];

    return $stats;
  }

}