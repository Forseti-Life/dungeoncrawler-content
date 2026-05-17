<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
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
   * Database connection for legacy compatibility mirrors.
   */
  protected Connection $database;

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
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->integrationService = $integration_service;
    $this->generatedImageRepository = $generated_image_repository;
    $this->promptBuilder = $prompt_builder;
    $this->database = $database;
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
   *   Overrides (generate, user_prompt, style, aspect_ratio).
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

    $force_regenerate = !empty($options['force_regenerate']);
    if (!$force_regenerate && $this->hasExistingPortrait($character_id, $campaign_id)) {
      $existing_url = $this->syncExistingPortraitUrl($character_id, $campaign_id);
      return [
        'attempted' => FALSE,
        'reason' => 'already_exists',
        'storage' => [
          'url' => $existing_url,
        ],
      ];
    }

    $integration_status = $this->integrationService->getIntegrationStatus();
    $requested_provider = strtolower(trim((string) ($options['provider'] ?? '')));
    $configured_provider = strtolower(trim((string) ($integration_status['configured_provider'] ?? $integration_status['default_provider'] ?? 'gemini')));
    $provider = $this->integrationService->getReadyProvider($requested_provider !== '' ? $requested_provider : $configured_provider);
    if ($provider === NULL) {
      $provider = $requested_provider !== '' ? $requested_provider : $configured_provider;
    }
    $provider_status = is_array($integration_status['providers'][$provider] ?? NULL)
      ? $integration_status['providers'][$provider]
      : [];
    $has_credentials = !empty($provider_status['has_credentials']) || !empty($provider_status['has_api_key']);
    if (empty($provider_status['enabled']) || !$has_credentials) {
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
      'aspect_ratio' => (string) ($options['aspect_ratio'] ?? '3:4'),
      'campaign_context' => (string) ($options['campaign_context'] ?? 'character_creation'),
      'requested_by_uid' => $owner_uid,
    ];

    try {
      $result = $this->integrationService->generateImage($payload, $provider);
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
      $this->syncLegacyPortraitColumn($character_id, (string) ($storage['url'] ?? ''));

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
   * Sync an existing generated portrait URL into the legacy portrait column.
   */
  private function syncExistingPortraitUrl(int $character_id, ?int $campaign_id): string {
    $images = $this->generatedImageRepository->loadImagesForObject(
      'dc_campaign_characters',
      (string) $character_id,
      $campaign_id,
      'portrait',
      'original'
    );
    $url = '';
    if (!empty($images[0]['public_url']) && is_string($images[0]['public_url'])) {
      $url = $images[0]['public_url'];
    }
    elseif (!empty($images[0]['url']) && is_string($images[0]['url'])) {
      $url = $images[0]['url'];
    }

    $this->syncLegacyPortraitColumn($character_id, $url);
    return $url;
  }

  /**
   * Writes the generated portrait URL into the legacy character row.
   */
  private function syncLegacyPortraitColumn(int $character_id, string $portrait_url): void {
    $portrait_url = trim($portrait_url);
    if ($character_id <= 0 || $portrait_url === '') {
      return;
    }

    $this->database->update('dc_campaign_characters')
      ->fields([
        'portrait' => $portrait_url,
      ])
      ->condition('id', $character_id)
      ->execute();
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
