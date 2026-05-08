<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai_conversation\Service\AIApiService;

/**
 * Encounter AI provider backed by the ai_conversation integration layer.
 */
class AiConversationEncounterAiProvider implements EncounterAiProviderInterface {

  /**
   * Shared AI API integration service.
   */
  protected ?AIApiService $aiApiService;

  /**
   * Logger factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Deterministic fallback provider.
   */
  protected StubEncounterAiProvider $fallbackProvider;

  /**
   * AI session manager for per-campaign encounter isolation.
   */
  protected AiSessionManager $sessionManager;

  /**
   * Constructs provider.
   */
  public function __construct(?AIApiService $ai_api_service, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory, StubEncounterAiProvider $fallback_provider, AiSessionManager $session_manager) {
    $this->aiApiService = $ai_api_service;
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
    $this->fallbackProvider = $fallback_provider;
    $this->sessionManager = $session_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function recommendNpcAction(array $context): array {
    $campaign_id = (int) ($context['campaign_id'] ?? 0);
    $current_actor = is_array($context['current_actor'] ?? NULL) ? $context['current_actor'] : [];
    $entity_ref = (string) ($current_actor['entity_ref'] ?? '');

    // Build NPC-scoped session context so each NPC keeps its own perspective.
    $npc_session_context = '';
    if ($campaign_id > 0 && $entity_ref !== '') {
      $npc_key = $this->sessionManager->npcSessionKey($campaign_id, $entity_ref);
      $npc_session_context = $this->sessionManager->buildSessionContext($npc_key, $campaign_id, 6);
    }

    $prompt = $this->buildRecommendationPrompt($context);
    if ($npc_session_context !== '') {
      $prompt = $npc_session_context . "\n\n---\nCURRENT TACTICAL REQUEST:\n" . $prompt;
    }

    $response = $this->invokeWithRetry(
      $prompt,
      'encounter_npc_recommendation',
      $this->buildContextData($context),
      [
        'max_tokens' => $this->getRecommendationMaxTokens(),
        'skip_cache' => TRUE,
        'system_prompt' => $this->buildRecommendationSystemPrompt(),
      ]
    );

    if (empty($response['success'])) {
      return $this->fallbackRecommendation(
        $context,
        (string) ($response['error'] ?? 'AI response was not successful.'),
        (int) ($response['request_attempts'] ?? 1),
        (string) ($response['request_id'] ?? '')
      );
    }

    $parsed = $this->decodeModelResponse((string) ($response['response'] ?? ''));
    if (!is_array($parsed)) {
      return $this->fallbackRecommendation(
        $context,
        'Unable to parse recommendation payload from ai_conversation response.',
        (int) ($response['request_attempts'] ?? 1),
        (string) ($response['request_id'] ?? '')
      );
    }

    $normalized = $this->normalizeRecommendation($parsed, $context);
    if ($normalized === NULL) {
      return $this->fallbackRecommendation(
        $context,
        'Recommendation payload missing required fields.',
        (int) ($response['request_attempts'] ?? 1),
        (string) ($response['request_id'] ?? '')
      );
    }

    $normalized['request_attempts'] = (int) ($response['request_attempts'] ?? 1);
    $normalized['request_id'] = (string) ($response['request_id'] ?? '');

    // Record the NPC's decision in its session for conversation continuity.
    if ($campaign_id > 0 && $entity_ref !== '') {
      $npc_key = $this->sessionManager->npcSessionKey($campaign_id, $entity_ref);
      $action_summary = ($normalized['recommended_action']['type'] ?? 'unknown')
        . ' → ' . ($normalized['recommended_action']['target_instance_id'] ?? 'none');
      $this->sessionManager->appendMessage($npc_key, $campaign_id, 'assistant', $action_summary, [
        'action_type' => $normalized['recommended_action']['type'] ?? '',
        'confidence' => $normalized['confidence'] ?? 0.5,
      ]);
    }

    return $normalized;
  }

  /**
   * {@inheritdoc}
   */
  public function generateEncounterNarration(array $context): array {
    $response = $this->invokeWithRetry(
      $this->buildNarrationPrompt($context),
      'encounter_narration',
      $this->buildContextData($context),
      [
        'max_tokens' => $this->getNarrationMaxTokens(),
        'skip_cache' => TRUE,
        'system_prompt' => $this->buildNarrationSystemPrompt(),
      ]
    );

    if (empty($response['success'])) {
      return $this->fallbackNarration(
        $context,
        (string) ($response['error'] ?? 'AI response was not successful.'),
        (int) ($response['request_attempts'] ?? 1),
        (string) ($response['request_id'] ?? '')
      );
    }

    $parsed = $this->decodeModelResponse((string) ($response['response'] ?? ''));
    if (!is_array($parsed)) {
      return $this->fallbackNarration(
        $context,
        'Unable to parse narration payload from ai_conversation response.',
        (int) ($response['request_attempts'] ?? 1),
        (string) ($response['request_id'] ?? '')
      );
    }

    $narration = trim((string) ($parsed['narration'] ?? ''));
    if ($narration === '') {
      return $this->fallbackNarration(
        $context,
        'Narration payload missing required narration field.',
        (int) ($response['request_attempts'] ?? 1),
        (string) ($response['request_id'] ?? '')
      );
    }

    return [
      'provider' => $this->getProviderName(),
      'round' => (int) ($context['current_round'] ?? 1),
      'narration' => $narration,
      'style' => (string) ($parsed['style'] ?? 'neutral-tactical'),
      'fallback_used' => FALSE,
      'request_attempts' => (int) ($response['request_attempts'] ?? 1),
      'request_id' => (string) ($response['request_id'] ?? ''),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getProviderName(): string {
    return 'ai_conversation';
  }

  /**
   * Build prompt for recommendation output.
   *
   * @param array<string, mixed> $context
   *   Encounter context payload.
   */
  private function buildRecommendationPrompt(array $context): string {
    $current_actor = is_array($context['current_actor'] ?? NULL) ? $context['current_actor'] : [];
    $current_actor_profile = is_array($context['current_actor_profile'] ?? NULL) ? $context['current_actor_profile'] : [];
    $allowed_actions = is_array($context['allowed_actions'] ?? NULL) ? $context['allowed_actions'] : [];
    $participants = is_array($context['participants'] ?? NULL) ? $context['participants'] : [];
    $visible_references = is_array($context['visible_references'] ?? NULL) ? $context['visible_references'] : [];
    $line_of_sight = is_array($context['line_of_sight'] ?? NULL) ? $context['line_of_sight'] : [];
    $conversation_options = is_array($context['conversation_options'] ?? NULL) ? $context['conversation_options'] : [];

    return json_encode([
      'task' => 'Choose a single legal tactical action for the active NPC combatant.',
      'constraints' => [
        'output_json_only' => TRUE,
        'allowed_actions' => $allowed_actions,
        'must_match_active_actor' => TRUE,
        'action_cost_max' => (int) ($current_actor['actions_remaining'] ?? 3),
        'conversation_allowed_when_visible' => TRUE,
      ],
      'encounter' => [
        'campaign_id' => (int) ($context['campaign_id'] ?? 0),
        'encounter_id' => (int) ($context['encounter_id'] ?? 0),
        'status' => (string) ($context['status'] ?? 'unknown'),
        'current_round' => (int) ($context['current_round'] ?? 1),
        'turn_index' => (int) ($context['turn_index'] ?? 0),
        'current_actor' => $current_actor,
        'current_actor_profile' => $current_actor_profile,
        'visible_references' => $visible_references,
        'line_of_sight' => $line_of_sight,
        'conversation_options' => $conversation_options,
        'participants' => $participants,
      ],
      'required_response_schema' => [
        'version' => 'v1',
        'actor_instance_id' => 'string',
        'recommended_action' => [
          'type' => 'string',
          'target_instance_id' => 'string|null',
          'action_cost' => 'integer',
          'parameters' => [
            'message' => 'string_optional_for_talk',
            'notes' => 'object_optional',
          ],
        ],
        'alternatives' => 'array',
        'rationale' => 'string',
        'confidence' => 'number_between_0_and_1',
      ],
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
  }

  /**
   * Build prompt for narration output.
   *
   * @param array<string, mixed> $context
   *   Encounter context payload.
   */
  private function buildNarrationPrompt(array $context): string {
    $current_actor = is_array($context['current_actor'] ?? NULL) ? $context['current_actor'] : [];

    return json_encode([
      'task' => 'Write one short tactical narration sentence for the active turn.',
      'constraints' => [
        'output_json_only' => TRUE,
        'tone' => 'neutral-tactical',
        'max_length_words' => 40,
      ],
      'encounter' => [
        'campaign_id' => (int) ($context['campaign_id'] ?? 0),
        'encounter_id' => (int) ($context['encounter_id'] ?? 0),
        'current_round' => (int) ($context['current_round'] ?? 1),
        'current_actor' => $current_actor,
      ],
      'required_response_schema' => [
        'narration' => 'string',
        'style' => 'string',
      ],
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
  }

  /**
   * Build system prompt for recommendation requests.
   */
  private function buildRecommendationSystemPrompt(): string {
    return 'You are a tactical combat assistant. Return valid JSON only with no markdown fences or extra prose.';
  }

  /**
   * Build system prompt for narration requests.
   */
  private function buildNarrationSystemPrompt(): string {
    return 'You are a concise encounter narrator. Return valid JSON only with fields narration and style.';
  }

  /**
   * Build context fields for ai_conversation usage tracking.
   *
   * @param array<string, mixed> $context
   *   Encounter context payload.
   *
   * @return array<string, mixed>
   *   Tracking context.
   */
  private function buildContextData(array $context): array {
    $current_actor = is_array($context['current_actor'] ?? NULL) ? $context['current_actor'] : [];

    return [
      'campaign_id' => (int) ($context['campaign_id'] ?? 0),
      'encounter_id' => (int) ($context['encounter_id'] ?? 0),
      'current_round' => (int) ($context['current_round'] ?? 1),
      'current_actor_entity_ref' => (string) ($current_actor['entity_ref'] ?? ''),
    ];
  }

  /**
   * Invoke ai_conversation model call with retry policy.
   *
   * @param string $prompt
   *   Prompt payload.
   * @param string $operation
   *   Operation identifier for usage tracking.
   * @param array<string, mixed> $context_data
   *   Tracking context payload.
   * @param array<string, mixed> $options
   *   Invocation options.
   *
   * @return array<string, mixed>
   *   Response envelope with request attempts metadata.
   */
  private function invokeWithRetry(string $prompt, string $operation, array $context_data, array $options): array {
    $max_attempts = $this->getMaxAttempts();
    $request_id = $this->buildRequestId($operation, $context_data);

    if ($this->aiApiService === NULL) {
      return [
        'success' => FALSE,
        'error' => 'ai_conversation.ai_api_service is not available (module disabled or misconfigured).',
        'request_attempts' => 1,
        'request_id' => $request_id,
      ];
    }

    $last_response = [
      'success' => FALSE,
      'error' => 'AI call attempts exhausted.',
    ];

    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
      $response = $this->aiApiService->invokeModelDirect(
        $prompt,
        'dungeoncrawler_content',
        $operation,
        $context_data + [
          'attempt' => $attempt,
          'request_id' => $request_id,
        ],
        $options
      );

      if (!empty($response['success'])) {
        $response['request_attempts'] = $attempt;
        $response['request_id'] = $request_id;
        return $response;
      }

      $last_response = is_array($response) ? $response : $last_response;
      $this->loggerFactory->get('dungeoncrawler_content')->warning('Encounter AI provider attempt failed.', [
        'provider' => $this->getProviderName(),
        'operation' => $operation,
        'attempt' => $attempt,
        'max_attempts' => $max_attempts,
        'error' => (string) ($last_response['error'] ?? 'Unknown failure'),
      ]);
    }

    $last_response['request_attempts'] = $max_attempts;
    $last_response['request_id'] = $request_id;
    return $last_response;
  }

  /**
   * Build stable request identifier for grouped retry tracking.
   *
   * @param string $operation
   *   AI operation name.
   * @param array<string, mixed> $context_data
   *   Encounter context metadata.
   */
  private function buildRequestId(string $operation, array $context_data): string {
    $encounter_id = (int) ($context_data['encounter_id'] ?? 0);
    $round = (int) ($context_data['current_round'] ?? 0);
    $entropy = microtime(TRUE) . '|' . mt_rand();

    return substr(hash('sha256', $operation . '|' . $encounter_id . '|' . $round . '|' . $entropy), 0, 20);
  }

  /**
   * Resolve maximum attempts per encounter AI request.
   */
  private function getMaxAttempts(): int {
    $config = $this->configFactory->get('dungeoncrawler_content.settings');
    $configured = (int) ($config->get('encounter_ai_retry_attempts') ?? 2);
    return max(1, min(3, $configured));
  }

  /**
   * Resolve max tokens for recommendation requests.
   */
  private function getRecommendationMaxTokens(): int {
    $config = $this->configFactory->get('dungeoncrawler_content.settings');
    $configured = (int) ($config->get('encounter_ai_recommendation_max_tokens') ?? 800);
    return max(200, min(2000, $configured));
  }

  /**
   * Resolve max tokens for narration requests.
   */
  private function getNarrationMaxTokens(): int {
    $config = $this->configFactory->get('dungeoncrawler_content.settings');
    $configured = (int) ($config->get('encounter_ai_narration_max_tokens') ?? 500);
    return max(120, min(1200, $configured));
  }

  /**
   * Decode model response JSON, with markdown fence fallback parsing.
   */
  private function decodeModelResponse(string $response): ?array {
    $trimmed = trim($response);
    if ($trimmed === '') {
      return NULL;
    }

    $decoded = json_decode($trimmed, TRUE);
    if (is_array($decoded)) {
      return $decoded;
    }

    if (preg_match('/```(?:json)?\\s*(\{.*\})\\s*```/is', $trimmed, $matches) === 1) {
      $decoded = json_decode($matches[1], TRUE);
      if (is_array($decoded)) {
        return $decoded;
      }
    }

    $start = strpos($trimmed, '{');
    $end = strrpos($trimmed, '}');
    if ($start !== FALSE && $end !== FALSE && $end > $start) {
      $snippet = substr($trimmed, $start, $end - $start + 1);
      $decoded = json_decode($snippet, TRUE);
      if (is_array($decoded)) {
        return $decoded;
      }
    }

    return NULL;
  }

  /**
   * Normalize recommendation payload into expected schema.
   *
   * @param array<string, mixed> $payload
   *   Parsed AI output payload.
   * @param array<string, mixed> $context
   *   Encounter context payload.
   *
   * @return array<string, mixed>|null
   *   Normalized recommendation or NULL when invalid.
   */
  private function normalizeRecommendation(array $payload, array $context): ?array {
    $current_actor = is_array($context['current_actor'] ?? NULL) ? $context['current_actor'] : [];
    $recommended_action = is_array($payload['recommended_action'] ?? NULL) ? $payload['recommended_action'] : [];

    $action_type = trim((string) ($recommended_action['type'] ?? $payload['action_type'] ?? ''));
    if ($action_type === '') {
      return NULL;
    }

    $action_cost = (int) ($recommended_action['action_cost'] ?? 1);
    if ($action_cost <= 0) {
      $action_cost = 1;
    }

    $confidence = (float) ($payload['confidence'] ?? 0.5);
    $confidence = max(0.0, min(1.0, $confidence));

    $target = $recommended_action['target_instance_id'] ?? $payload['target_instance_id'] ?? NULL;
    $target_instance_id = is_scalar($target) ? trim((string) $target) : '';

    $parameters = is_array($recommended_action['parameters'] ?? NULL) ? $recommended_action['parameters'] : [];
    $alternatives = is_array($payload['alternatives'] ?? NULL) ? $payload['alternatives'] : [];
    $actor_instance_id = trim((string) ($payload['actor_instance_id'] ?? $current_actor['entity_ref'] ?? ''));

    if ($actor_instance_id === '') {
      return NULL;
    }

    return [
      'version' => (string) ($payload['version'] ?? 'v1'),
      'provider' => $this->getProviderName(),
      'actor_instance_id' => $actor_instance_id,
      'recommended_action' => [
        'type' => $action_type,
        'target_instance_id' => $target_instance_id !== '' ? $target_instance_id : NULL,
        'action_cost' => $action_cost,
        'parameters' => $parameters,
      ],
      'alternatives' => $alternatives,
      'rationale' => (string) ($payload['rationale'] ?? 'Selected by ai_conversation tactical provider.'),
      'confidence' => $confidence,
      'fallback_used' => FALSE,
    ];
  }

  /**
   * Build fallback recommendation and log provider error details.
   *
   * @param array<string, mixed> $context
   *   Encounter context payload.
   * @param string $reason
   *   Fallback reason.
   *
   * @return array<string, mixed>
   *   Deterministic fallback recommendation.
   */
  private function fallbackRecommendation(array $context, string $reason, int $request_attempts, string $request_id): array {
    $this->loggerFactory->get('dungeoncrawler_content')->warning('Encounter AI recommendation fell back to deterministic provider.', [
      'provider' => $this->getProviderName(),
      'reason' => $reason,
      'encounter_id' => (int) ($context['encounter_id'] ?? 0),
      'campaign_id' => (int) ($context['campaign_id'] ?? 0),
    ]);

    $fallback = $this->fallbackProvider->recommendNpcAction($context);
    $fallback['provider'] = $this->getProviderName();
    $fallback['fallback_used'] = TRUE;
    $fallback['fallback_reason'] = $reason;
    $fallback['request_attempts'] = max(1, $request_attempts);
    $fallback['request_id'] = $request_id;

    return $fallback;
  }

  /**
   * Build fallback narration and log provider error details.
   *
   * @param array<string, mixed> $context
   *   Encounter context payload.
   * @param string $reason
   *   Fallback reason.
   *
   * @return array<string, mixed>
   *   Fallback narration payload.
   */
  private function fallbackNarration(array $context, string $reason, int $request_attempts, string $request_id): array {
    $this->loggerFactory->get('dungeoncrawler_content')->warning('Encounter narration fell back to deterministic provider.', [
      'provider' => $this->getProviderName(),
      'reason' => $reason,
      'encounter_id' => (int) ($context['encounter_id'] ?? 0),
      'campaign_id' => (int) ($context['campaign_id'] ?? 0),
    ]);

    $fallback = $this->fallbackProvider->generateEncounterNarration($context);
    $fallback['provider'] = $this->getProviderName();
    $fallback['fallback_used'] = TRUE;
    $fallback['fallback_reason'] = $reason;
    $fallback['request_attempts'] = max(1, $request_attempts);
    $fallback['request_id'] = $request_id;

    return $fallback;
  }

}
