<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Generates and persists character portrait images.
 */
class CharacterPortraitGenerationService {

  /**
   * Image generation integration service.
   */
  protected ImageGenerationIntegrationService $integrationService;

  /**
   * Generated image repository.
   */
  protected GeneratedImageRepository $generatedImageRepository;

  /**
   * Prompt builder.
   */
  protected CharacterImagePromptBuilder $promptBuilder;

  /**
   * Logger channel.
   */
  protected $logger;

  /**
   * Constructs the service.
   */
  public function __construct(
    ImageGenerationIntegrationService $integration_service,
    GeneratedImageRepository $generated_image_repository,
    CharacterImagePromptBuilder $prompt_builder,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->integrationService = $integration_service;
    $this->generatedImageRepository = $generated_image_repository;
    $this->promptBuilder = $prompt_builder;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * Generates a portrait for a character and persists it when possible.
   *
   * @param array $character_data
   *   Character data payload.
   * @param int $character_id
   *   Character record id.
   * @param int $owner_uid
   *   Owner user id.
   * @param int|null $campaign_id
   *   Campaign id (if available).
   * @param array $options
   *   Overrides (generate, user_prompt, style, aspect_ratio, negative_prompt).
   *
   * @return array
   *   Generation summary, including raw provider result and storage info.
   */
  public function generatePortrait(array $character_data, int $character_id, int $owner_uid, ?int $campaign_id = NULL, array $options = []): array {
    if ($character_id <= 0) {
      return [
        'attempted' => FALSE,
        'reason' => 'missing_character_id',
      ];
    }

    $should_generate = $this->normalizeBoolean($options['generate'] ?? ($character_data['portrait_generate'] ?? NULL));
    if (!$should_generate) {
      return [
        'attempted' => FALSE,
        'reason' => 'disabled',
      ];
    }

    if ($this->hasExistingPortrait($character_id, $campaign_id)) {
      return [
        'attempted' => FALSE,
        'reason' => 'already_exists',
      ];
    }

    $provider = strtolower(trim((string) ($options['provider'] ?? '')));
    $integration_status = $this->integrationService->getIntegrationStatus();
    if ($provider === '') {
      $provider = strtolower(trim((string) ($integration_status['default_provider'] ?? 'gemini')));
    }
    $provider_status = is_array($integration_status['providers'][$provider] ?? NULL)
      ? $integration_status['providers'][$provider]
      : [];
    if (empty($provider_status['enabled']) || empty($provider_status['has_api_key'])) {
      $this->logger->warning('Character portrait generation unavailable for character @character_id: provider @provider is not fully configured.', [
        '@character_id' => $character_id,
        '@provider' => $provider,
      ]);
      return [
        'attempted' => FALSE,
        'reason' => 'provider_unavailable',
        'provider' => $provider,
        'provider_status' => $provider_status,
      ];
    }

    $user_prompt = (string) ($options['user_prompt'] ?? ($character_data['portrait_prompt'] ?? ''));
    $prompt = $this->promptBuilder->buildPortraitPrompt($character_data, $user_prompt);

    $payload = [
      'prompt' => $prompt,
      'style' => (string) ($options['style'] ?? 'fantasy'),
      'aspect_ratio' => (string) ($options['aspect_ratio'] ?? '1:1'),
      'negative_prompt' => (string) ($options['negative_prompt'] ?? $this->promptBuilder->getDefaultNegativePrompt()),
      'campaign_context' => (string) ($options['campaign_context'] ?? 'character_creation'),
      'requested_by_uid' => $owner_uid,
    ];

    try {
      $result = $this->integrationService->generateImage($payload, $options['provider'] ?? NULL);
      $storage = $this->generatedImageRepository->persistGeneratedImage($result, [
        'owner_uid' => $owner_uid,
        'scope_type' => 'campaign',
        'campaign_id' => $campaign_id,
        'table_name' => 'dc_campaign_characters',
        'object_id' => (string) $character_id,
        'slot' => 'portrait',
        'variant' => 'original',
        'visibility' => 'owner',
        'is_primary' => 1,
      ]);

      return [
        'attempted' => TRUE,
        'provider' => $provider,
        'result' => $result,
        'storage' => $storage,
      ];
    }
    catch (\Throwable $e) {
      $this->logger->error('Character portrait generation failed: @message', [
        '@message' => $e->getMessage(),
      ]);

      return [
        'attempted' => TRUE,
        'reason' => 'exception',
      ];
    }
  }

  /**
   * Checks for existing portrait images.
   */
  private function hasExistingPortrait(int $character_id, ?int $campaign_id): bool {
    $images = $this->generatedImageRepository->loadImagesForObject(
      'dc_campaign_characters',
      (string) $character_id,
      $campaign_id,
      'portrait',
      'original'
    );

    return !empty($images);
  }

  /**
   * Normalizes a boolean-like value.
   */
  private function normalizeBoolean($value): bool {
    if (is_bool($value)) {
      return $value;
    }

    if (is_numeric($value)) {
      return ((int) $value) === 1;
    }

    if (is_string($value)) {
      $normalized = strtolower(trim($value));
      return in_array($normalized, ['1', 'true', 'yes', 'on'], TRUE);
    }

    return FALSE;
  }

}
