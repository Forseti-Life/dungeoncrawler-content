<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Optional bridge to the external Forseti TTS module.
 */
class TextToSpeechIntegrationService {

  /**
   * External service ID.
   */
  private const PROVIDER_SERVICE_ID = 'forseti_tts.google_text_to_speech';

  /**
   * Service container.
   */
  protected ContainerInterface $serviceContainer;

  /**
   * Logger channel.
   */
  protected $logger;

  /**
   * Constructs the TTS bridge.
   */
  public function __construct(ContainerInterface $service_container, LoggerChannelFactoryInterface $logger_factory) {
    $this->serviceContainer = $service_container;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * Returns availability and provider status.
   *
   * @return array<string, mixed>
   *   Integration status.
   */
  public function getIntegrationStatus(): array {
    $service = $this->getProviderService();
    if ($service === NULL) {
      return [
        'available' => FALSE,
        'service_id' => self::PROVIDER_SERVICE_ID,
        'message' => 'The Forseti TTS module is not installed or enabled in this Drupal site.',
      ];
    }

    if (!method_exists($service, 'getIntegrationStatus')) {
      return [
        'available' => FALSE,
        'service_id' => self::PROVIDER_SERVICE_ID,
        'message' => 'The Forseti TTS service is present but does not expose getIntegrationStatus().',
      ];
    }

    try {
      $provider_status = $service->getIntegrationStatus();
      return [
        'available' => TRUE,
        'service_id' => self::PROVIDER_SERVICE_ID,
        'provider_status' => is_array($provider_status) ? $provider_status : [],
        'message' => 'Forseti TTS service is available.',
      ];
    }
    catch (\Throwable $e) {
      $this->logger->error('Forseti TTS status request failed: @message', [
        '@message' => $e->getMessage(),
      ]);

      return [
        'available' => FALSE,
        'service_id' => self::PROVIDER_SERVICE_ID,
        'message' => 'Forseti TTS status request failed: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Synthesizes text through the external provider module.
   *
   * @param string $text
   *   Input text.
   * @param array<string, mixed> $options
   *   Optional voice overrides.
   *
   * @return array<string, mixed>
   *   Result payload.
   */
  public function synthesizeSpeech(string $text, array $options = []): array {
    $service = $this->getProviderService();
    if ($service === NULL) {
      return [
        'success' => FALSE,
        'message' => 'Forseti TTS is unavailable because the external module service is missing.',
      ];
    }

    if (!method_exists($service, 'synthesizeSpeech')) {
      return [
        'success' => FALSE,
        'message' => 'The Forseti TTS service is present but does not expose synthesizeSpeech().',
      ];
    }

    try {
      $result = $service->synthesizeSpeech($text, $options);
      return is_array($result) ? $result : [
        'success' => FALSE,
        'message' => 'Forseti TTS returned an unexpected response shape.',
      ];
    }
    catch (\Throwable $e) {
      $this->logger->error('Forseti TTS synthesis request failed: @message', [
        '@message' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'message' => 'Forseti TTS synthesis request failed: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Stores a synthesized result through the external provider module.
   *
   * @param array<string, mixed> $result
   *   Synthesis result payload.
   *
   * @return array<string, mixed>
   *   Storage payload.
   */
  public function storeAudioResult(array $result, string $directory = 'public://forseti-tts-tests'): array {
    $service = $this->getProviderService();
    if ($service === NULL) {
      return [
        'success' => FALSE,
        'message' => 'Forseti TTS is unavailable because the external module service is missing.',
      ];
    }

    if (!method_exists($service, 'storeAudioResult')) {
      return [
        'success' => FALSE,
        'message' => 'The Forseti TTS service is present but does not expose storeAudioResult().',
      ];
    }

    try {
      $stored = $service->storeAudioResult($result, $directory);
      return is_array($stored) ? $stored : [
        'success' => FALSE,
        'message' => 'Forseti TTS storage returned an unexpected response shape.',
      ];
    }
    catch (\Throwable $e) {
      $this->logger->error('Forseti TTS audio storage failed: @message', [
        '@message' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'message' => 'Forseti TTS audio storage failed: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Returns the external provider service when available.
   */
  protected function getProviderService(): ?object {
    if (!$this->serviceContainer->has(self::PROVIDER_SERVICE_ID)) {
      return NULL;
    }

    $service = $this->serviceContainer->get(self::PROVIDER_SERVICE_ID);
    return is_object($service) ? $service : NULL;
  }

}
