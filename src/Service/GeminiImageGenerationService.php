<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Stub integration service for Gemini image generation.
 */
class GeminiImageGenerationService {

  /**
   * Logger channel for module integration telemetry.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * HTTP client for provider calls.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * Constructs a GeminiImageGenerationService.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, TimeInterface $time, ConfigFactoryInterface $config_factory, ClientInterface $http_client) {
    $this->loggerFactory = $logger_factory;
    $this->time = $time;
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
  }

  /**
   * Return integration status for dashboard display.
   *
   * @return array<string, mixed>
   *   Integration status values.
   */
  public function getIntegrationStatus(): array {
    $config = $this->getSettings();
    $api_key = $this->resolveApiKey($config);

    return [
      'enabled' => (bool) $config->get('gemini_image_enabled'),
      'has_api_key' => $api_key !== '',
      'api_key_source' => $config->get('gemini_image_api_key') ? 'config' : (getenv('GEMINI_API_KEY') ? 'env' : 'none'),
      'model' => $this->resolveModel($config),
      'endpoint' => $this->resolveEndpointTemplate($config),
      'timeout' => $this->resolveTimeout($config),
    ];
  }

  /**
   * Builds a normalized stub response for Gemini image generation.
   *
   * @param array $payload
   *   Input request payload from the dashboard form.
   *
   * @return array
   *   Stubbed response payload.
   */
  public function generateImage(array $payload): array {
    $timestamp = $this->time->getCurrentTime();
    $request_id = sprintf('gemini-stub-%d-%d', $timestamp, random_int(1000, 9999));
    $config = $this->getSettings();
    $status = $this->getIntegrationStatus();

    $normalized_payload = [
      'prompt' => trim((string) ($payload['prompt'] ?? '')),
      'system_prompt' => trim((string) ($payload['system_prompt'] ?? '')),
      'wrapped_prompt' => trim((string) ($payload['wrapped_prompt'] ?? '')),
      'style' => trim((string) ($payload['style'] ?? 'fantasy')),
      'aspect_ratio' => trim((string) ($payload['aspect_ratio'] ?? '1:1')),
      'negative_prompt' => trim((string) ($payload['negative_prompt'] ?? '')),
      'campaign_context' => trim((string) ($payload['campaign_context'] ?? '')),
      'requested_by_uid' => (int) ($payload['requested_by_uid'] ?? 0),
      'requested_at' => $timestamp,
    ];

    if (!$status['enabled'] || !$status['has_api_key']) {
      $mode = !$status['enabled'] ? 'stub' : 'stub_missing_api_key';
      $message = !$status['enabled']
        ? 'Stub accepted. External Gemini API call is not enabled in settings.'
        : 'Stub accepted. Gemini live mode enabled but no API key was found.';

      $this->loggerFactory->get('dungeoncrawler_content')->notice('Gemini image generation stub invoked.', [
        'request_id' => $request_id,
        'mode' => $mode,
        'prompt_length' => strlen($normalized_payload['prompt']),
        'style' => $normalized_payload['style'],
        'aspect_ratio' => $normalized_payload['aspect_ratio'],
        'requested_by_uid' => $normalized_payload['requested_by_uid'],
      ]);

      return [
        'success' => TRUE,
        'provider' => 'gemini',
        'mode' => $mode,
        'request_id' => $request_id,
        'status' => 'accepted_for_integration_stub',
        'message' => $message,
        'payload' => $normalized_payload,
      ];
    }

    $request_id = sprintf('gemini-live-%d-%d', $timestamp, random_int(1000, 9999));
    $api_key = $this->resolveApiKey($config);
    $model = $this->resolveModel($config);
    $endpoint = $this->buildEndpoint($this->resolveEndpointTemplate($config), $model, $api_key);
    $timeout = $this->resolveTimeout($config);
    $request_body = $this->buildGeminiRequestBody($normalized_payload);

    try {
      $response = $this->httpClient->request('POST', $endpoint, [
        'headers' => [
          'Accept' => 'application/json',
          'Content-Type' => 'application/json',
        ],
        'json' => $request_body,
        'timeout' => $timeout,
      ]);

      $decoded = json_decode((string) $response->getBody(), TRUE);
      if (!is_array($decoded)) {
        throw new \RuntimeException('Gemini response was not valid JSON.');
      }

      $parsed_output = $this->extractOutput($decoded);

      $this->loggerFactory->get('dungeoncrawler_content')->notice('Gemini image generation live request completed.', [
        'request_id' => $request_id,
        'http_status' => $response->getStatusCode(),
        'has_image' => $parsed_output['image_data_uri'] !== NULL || $parsed_output['image_url'] !== NULL,
      ]);

      return [
        'success' => TRUE,
        'provider' => 'gemini',
        'mode' => 'live',
        'request_id' => $request_id,
        'status' => 'completed',
        'message' => 'Gemini API request completed.',
        'payload' => $normalized_payload,
        'output' => $parsed_output,
      ];
    }
    catch (GuzzleException | \RuntimeException $exception) {
      $this->loggerFactory->get('dungeoncrawler_content')->error('Gemini image generation request failed.', [
        'request_id' => $request_id,
        'message' => $exception->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'provider' => 'gemini',
        'mode' => 'live',
        'request_id' => $request_id,
        'status' => 'failed',
        'message' => 'Gemini request failed: ' . $exception->getMessage(),
        'payload' => $normalized_payload,
      ];
    }
  }

  /**
   * Return module settings config.
   */
  private function getSettings(): ImmutableConfig {
    return $this->configFactory->get('dungeoncrawler_content.settings');
  }

  /**
   * Resolve API key from config first, then environment.
   */
  private function resolveApiKey(ImmutableConfig $config): string {
    $configured_key = trim((string) $config->get('gemini_image_api_key'));
    if ($configured_key !== '') {
      return $configured_key;
    }

    $env_key = getenv('GEMINI_API_KEY');
    if (is_string($env_key)) {
      return trim($env_key);
    }

    return '';
  }

  /**
   * Resolve configured model name.
   */
  private function resolveModel(ImmutableConfig $config): string {
    $model = trim((string) $config->get('gemini_image_model'));
    return $model !== '' ? $model : 'gemini-2.0-flash-exp';
  }

  /**
   * Resolve configured endpoint template.
   */
  private function resolveEndpointTemplate(ImmutableConfig $config): string {
    $endpoint = trim((string) $config->get('gemini_image_endpoint'));
    return $endpoint !== '' ? $endpoint : 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent';
  }

  /**
   * Resolve configured request timeout.
   */
  private function resolveTimeout(ImmutableConfig $config): int {
    $timeout = (int) $config->get('gemini_image_timeout');
    return $timeout >= 5 ? $timeout : 30;
  }

  /**
   * Build endpoint URL with model and API key.
   */
  private function buildEndpoint(string $template, string $model, string $api_key): string {
    $endpoint = str_replace('{model}', rawurlencode($model), $template);

    if (strpos($endpoint, '{model}') !== FALSE) {
      $endpoint = str_replace('{model}', $model, $endpoint);
    }

    if (strpos($endpoint, 'key=') === FALSE) {
      $separator = strpos($endpoint, '?') === FALSE ? '?' : '&';
      $endpoint .= $separator . 'key=' . rawurlencode($api_key);
    }

    return $endpoint;
  }

  /**
   * Build Gemini request body from normalized payload.
   */
  private function buildGeminiRequestBody(array $normalized_payload): array {
    $prompt = $normalized_payload['wrapped_prompt'] !== '' ? $normalized_payload['wrapped_prompt'] : $normalized_payload['prompt'];
    $prompt .= "\n\nStyle: " . $normalized_payload['style'];
    $prompt .= "\nAspect ratio: " . $normalized_payload['aspect_ratio'];

    if ($normalized_payload['negative_prompt'] !== '') {
      $prompt .= "\nNegative prompt: " . $normalized_payload['negative_prompt'];
    }

    if ($normalized_payload['campaign_context'] !== '') {
      $prompt .= "\nCampaign context: " . $normalized_payload['campaign_context'];
    }

    return [
      'contents' => [
        [
          'role' => 'user',
          'parts' => [
            ['text' => $prompt],
          ],
        ],
      ],
      'generationConfig' => [
        'responseModalities' => ['TEXT', 'IMAGE'],
      ],
    ];
  }

  /**
   * Extract text/image output from Gemini response payload.
   *
   * @return array<string, string|null>
   *   Parsed output values.
   */
  private function extractOutput(array $response): array {
    $output = [
      'text' => NULL,
      'image_data_uri' => NULL,
      'image_url' => NULL,
    ];

    $candidates = $response['candidates'] ?? [];
    if (!is_array($candidates)) {
      return $output;
    }

    foreach ($candidates as $candidate) {
      if (!is_array($candidate)) {
        continue;
      }

      $parts = $candidate['content']['parts'] ?? [];
      if (!is_array($parts)) {
        continue;
      }

      foreach ($parts as $part) {
        if (!is_array($part)) {
          continue;
        }

        if ($output['text'] === NULL && !empty($part['text']) && is_string($part['text'])) {
          $output['text'] = $part['text'];
        }

        $inline = $part['inlineData'] ?? ($part['inline_data'] ?? NULL);
        if (is_array($inline) && $output['image_data_uri'] === NULL) {
          $mime = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png');
          $data = (string) ($inline['data'] ?? '');
          if (strpos($mime, 'image/') === 0 && $data !== '') {
            $output['image_data_uri'] = 'data:' . $mime . ';base64,' . $data;
          }
        }

        if ($output['image_url'] === NULL && !empty($part['imageUrl']) && is_string($part['imageUrl'])) {
          $output['image_url'] = $part['imageUrl'];
        }

        if ($output['image_url'] === NULL && !empty($part['fileData']['fileUri']) && is_string($part['fileData']['fileUri'])) {
          $output['image_url'] = $part['fileData']['fileUri'];
        }
      }
    }

    return $output;
  }

}
