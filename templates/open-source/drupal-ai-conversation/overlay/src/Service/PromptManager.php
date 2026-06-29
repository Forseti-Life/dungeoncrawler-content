<?php

namespace Drupal\ai_conversation\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Centralized prompt management service for AI conversations.
 */
class PromptManager {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a PromptManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * Get the base system prompt for the public module.
   *
   * @return string
   *   The system prompt text.
   */
  public function getBaseSystemPrompt() {
    return <<<'EOD'
You are a helpful AI assistant embedded in a Drupal site.

CORE BEHAVIOR:
- Give accurate, concise, context-aware answers.
- Use the active conversation and any site-provided context when available.
- If key information is missing, say so clearly and ask a short clarifying question.
- Do not claim to have executed actions, changed data, or verified results unless the system explicitly confirms it.

COMMUNICATION STYLE:
- Clear, calm, and practical.
- Prefer direct answers over long preambles.
- Explain important tradeoffs when they affect the user's decision.

SUGGESTION HANDLING:
When a user shares a feature request or improvement idea:
1. Summarize the idea in 1-2 sentences and ask whether they want it formally submitted.
2. If they confirm, emit:
   [CREATE_SUGGESTION]
   Summary: [Brief summary]
   Category: [feature_request, workflow_improvement, content_update, bug_report, integration_idea, general_feedback, other]
   Original: [User's original suggestion]
   [/CREATE_SUGGESTION]
3. Then respond: "Your suggestion has been logged for review. Thank you for the feedback."
EOD;
  }

  /**
   * Get the full system prompt with optional node content appended.
   *
   * @param int|null $node_id
   *   Optional node ID to load dynamic content from.
   *
   * @return string
   *   The complete system prompt with dynamic content.
   */
  public function getSystemPrompt($node_id = NULL) {
    $base_prompt = $this->getBaseSystemPrompt();

    if ($node_id) {
      $dynamic_content = $this->loadDynamicContent($node_id);
      if (!empty($dynamic_content)) {
        $base_prompt .= "\n\n--- ADDITIONAL SITE CONTENT ---\n\n" . $dynamic_content;
      }
    }

    return $base_prompt;
  }

  /**
   * Load dynamic content from a node.
   *
   * @param int $node_id
   *   The node ID to load.
   *
   * @return string
   *   The node content or an empty string if not found.
   */
  protected function loadDynamicContent($node_id) {
    try {
      $node = $this->entityTypeManager->getStorage('node')->load($node_id);

      if ($node && $node->access('view')) {
        $content = 'TITLE: ' . $node->getTitle() . "\n\n";

        if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
          $content .= strip_tags($node->get('body')->value);
        }

        return $content;
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error loading dynamic content from node @nid: @message', [
        '@nid' => $node_id,
        '@message' => $e->getMessage(),
      ]);
    }

    return '';
  }

  /**
   * Get a shortened summary prompt for fallback scenarios.
   *
   * @return string
   *   A brief description of the assistant.
   */
  public function getFallbackPrompt() {
    return 'AI Conversation assistant for Drupal sites. Provides persistent chat, configurable providers, and context-aware responses.';
  }

  /**
   * Save the base system prompt to configuration.
   *
   * @param string $prompt
   *   The prompt text to save.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function saveSystemPrompt($prompt) {
    try {
      $config = $this->configFactory->getEditable('ai_conversation.settings');
      $config->set('system_prompt', $prompt);
      $config->save();

      \Drupal::service('cache.config')->deleteAll();

      $this->logger->info('System prompt updated successfully. Length: @length', [
        '@length' => strlen($prompt),
      ]);

      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Error saving system prompt: @message', [
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Initialize the system prompt configuration with the default prompt.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function initializeDefaultPrompt() {
    return $this->saveSystemPrompt($this->getBaseSystemPrompt());
  }

  /**
   * Get configured system prompt from config or use default.
   *
   * @return string
   *   The system prompt.
   */
  public function getConfiguredPrompt() {
    $config = $this->configFactory->get('ai_conversation.settings');
    $prompt = $config->get('system_prompt');

    if (empty($prompt)) {
      $this->logger->warning('No system prompt found in configuration, using default');
      return $this->getBaseSystemPrompt();
    }

    return $prompt;
  }

}
