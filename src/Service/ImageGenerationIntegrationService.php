<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provider-agnostic integration layer for image generation.
 */
class ImageGenerationIntegrationService {

  /**
   * Default Gemini system context prompt.
   */
  private const DEFAULT_GEMINI_SYSTEM_PROMPT = "You are a production token-art generator for a hexmap tactical RPG client.\n\nGenerate original, non-infringing high-fantasy token images for: character, creature, item, obstacle, floortile.\n\nRequirements:\n- No copyrighted characters, logos, text labels, or watermarks.\n- Strong silhouette readability at small sizes.\n- Maintain consistent style and lighting.\n- Transparent PNG output for non-floortile tokens unless requested otherwise.\n- Center subject with clean composition and game-ready legibility.";

  /**
   * Config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Gemini provider service.
   */
  protected GeminiImageGenerationService $geminiImageService;

  /**
   * Vertex provider service.
   */
  protected VertexImageGenerationService $vertexImageService;

  /**
   * Constructs integration service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, GeminiImageGenerationService $gemini_image_service, VertexImageGenerationService $vertex_image_service) {
    $this->configFactory = $config_factory;
    $this->geminiImageService = $gemini_image_service;
    $this->vertexImageService = $vertex_image_service;
  }

  /**
   * Generates an image with selected provider.
   *
   * @param array<string, mixed> $payload
   *   Normalized payload.
   * @param string|null $provider
   *   Provider override (gemini|vertex).
   *
   * @return array<string, mixed>
   *   Provider response.
   */
  public function generateImage(array $payload, ?string $provider = NULL): array {
    $resolved_provider = $this->resolveProvider($provider);

    if ($resolved_provider === 'gemini') {
      $system_prompt = $this->getGeminiSystemContextPrompt();
      $payload['system_prompt'] = $system_prompt;
      $payload['wrapped_prompt'] = $this->wrapGeminiPrompt((string) ($payload['prompt'] ?? ''), $system_prompt);
    }

    return match ($resolved_provider) {
      'vertex' => $this->vertexImageService->generateImage($payload),
      default => $this->geminiImageService->generateImage($payload),
    };
  }

  /**
   * Returns configured Gemini system context prompt.
   */
  public function getGeminiSystemContextPrompt(): string {
    $configured = trim((string) $this->configFactory->get('dungeoncrawler_content.settings')->get('gemini_system_context_prompt'));
    return $configured !== '' ? $configured : self::DEFAULT_GEMINI_SYSTEM_PROMPT;
  }

  /**
   * Wraps a user prompt with Gemini system context.
   */
  public function wrapGeminiPrompt(string $user_prompt, ?string $system_prompt = NULL): string {
    $resolved_system_prompt = trim((string) ($system_prompt ?? $this->getGeminiSystemContextPrompt()));
    $resolved_user_prompt = trim($user_prompt);

    if ($resolved_system_prompt === '') {
      return $resolved_user_prompt;
    }

    return $resolved_system_prompt . "\n\nUser Request:\n" . $resolved_user_prompt;
  }

  /**
   * Returns dashboard status for all providers.
   *
   * @return array<string, mixed>
   *   Integration status data.
   */
  public function getIntegrationStatus(): array {
    return [
      'default_provider' => $this->resolveProvider(NULL),
      'providers' => [
        'gemini' => $this->geminiImageService->getIntegrationStatus(),
        'vertex' => $this->vertexImageService->getIntegrationStatus(),
      ],
    ];
  }

  /**
   * Resolve provider from override or configuration.
   */
  private function resolveProvider(?string $provider): string {
    $normalized = strtolower(trim((string) $provider));
    if (in_array($normalized, ['gemini', 'vertex'], TRUE)) {
      return $normalized;
    }

    $configured = strtolower(trim((string) $this->configFactory->get('dungeoncrawler_content.settings')->get('generated_image_provider')));
    return in_array($configured, ['gemini', 'vertex'], TRUE) ? $configured : 'gemini';
  }

}
