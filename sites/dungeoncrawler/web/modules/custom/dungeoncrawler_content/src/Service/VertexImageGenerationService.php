<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Vertex image generation integration service.
 */
class VertexImageGenerationService {

  /**
   * Logger factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Time service.
   */
  protected TimeInterface $time;

  /**
   * Config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Database connection.
   */
  protected Connection $database;

  /**
   * HTTP client.
   */
  protected ClientInterface $httpClient;

  /**
   * Constructs VertexImageGenerationService.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, TimeInterface $time, ConfigFactoryInterface $config_factory, Connection $database, ClientInterface $http_client) {
    $this->loggerFactory = $logger_factory;
    $this->time = $time;
    $this->configFactory = $config_factory;
    $this->database = $database;
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
      'enabled' => (bool) $config->get('vertex_image_enabled'),
      'has_api_key' => $api_key !== '',
      'api_key_source' => $config->get('vertex_image_api_key') ? 'config' : (getenv('VERTEX_API_KEY') ? 'env' : 'none'),
      'project_id' => $this->resolveProjectId($config),
      'location' => $this->resolveLocation($config),
      'model' => $this->resolveModel($config),
      'endpoint' => $this->resolveEndpointTemplate($config),
      'timeout' => $this->resolveTimeout($config),
    ];
  }

  /**
   * Generates an image using Vertex live mode or stub fallback.
   *
   * @param array<string, mixed> $payload
   *   Input request payload.
   *
   * @return array<string, mixed>
   *   Normalized generation result.
   */
  public function generateImage(array $payload): array {
    $timestamp = $this->time->getCurrentTime();
    $request_id = sprintf('vertex-stub-%d-%d', $timestamp, random_int(1000, 9999));
    $config = $this->getSettings();
    $status = $this->getIntegrationStatus();

    $normalized_payload = [
      'prompt' => trim((string) ($payload['prompt'] ?? '')),
      'style' => trim((string) ($payload['style'] ?? 'fantasy')),
      'aspect_ratio' => trim((string) ($payload['aspect_ratio'] ?? '1:1')),
      'negative_prompt' => trim((string) ($payload['negative_prompt'] ?? '')),
      'campaign_context' => trim((string) ($payload['campaign_context'] ?? '')),
      'requested_by_uid' => (int) ($payload['requested_by_uid'] ?? 0),
      'requested_at' => $timestamp,
      'campaign_id' => $this->normalizeInt($payload['campaign_id'] ?? NULL),
      'map_id' => $this->normalizeString($payload['map_id'] ?? ''),
      'dungeon_id' => $this->normalizeString($payload['dungeon_id'] ?? ($payload['dungeon'] ?? '')),
      'room_id' => $this->normalizeString($payload['room_id'] ?? ($payload['room'] ?? '')),
      'hex_q' => $this->normalizeInt($payload['hex_q'] ?? NULL),
      'hex_r' => $this->normalizeInt($payload['hex_r'] ?? NULL),
      'entity_type' => $this->normalizeString($payload['entity_type'] ?? ''),
      'terrain_type' => $this->normalizeString($payload['terrain_type'] ?? ''),
      'habitat_name' => $this->normalizeString($payload['habitat_name'] ?? ''),
    ];

    if (!$status['enabled'] || !$status['has_api_key']) {
      $mode = !$status['enabled'] ? 'stub' : 'stub_missing_api_key';
      $message = !$status['enabled']
        ? 'Stub accepted. External Vertex API call is not enabled in settings.'
        : 'Stub accepted. Vertex live mode enabled but no API key was found.';

      $this->loggerFactory->get('dungeoncrawler_content')->notice('Vertex image generation stub invoked.', [
        'request_id' => $request_id,
        'mode' => $mode,
        'prompt_length' => strlen($normalized_payload['prompt']),
        'style' => $normalized_payload['style'],
        'aspect_ratio' => $normalized_payload['aspect_ratio'],
        'requested_by_uid' => $normalized_payload['requested_by_uid'],
      ]);

      return [
        'success' => TRUE,
        'provider' => 'vertex',
        'mode' => $mode,
        'request_id' => $request_id,
        'status' => 'accepted_for_integration_stub',
        'message' => $message,
        'payload' => $normalized_payload,
      ];
    }

    $request_id = sprintf('vertex-live-%d-%d', $timestamp, random_int(1000, 9999));
    $api_key = $this->resolveApiKey($config);
    $project_id = $this->resolveProjectId($config);
    $location = $this->resolveLocation($config);
    $model = $this->resolveModel($config);

    $cached = $this->loadCachedResult($normalized_payload, $model);
    if ($cached !== NULL) {
      return $cached;
    }

    $endpoint = $this->buildEndpoint($this->resolveEndpointTemplate($config), $project_id, $location, $model, $api_key);
    $timeout = $this->resolveTimeout($config);
    $request_body = $this->buildVertexRequestBody($normalized_payload);

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
        throw new \RuntimeException('Vertex response was not valid JSON.');
      }

      $parsed_output = $this->extractOutput($decoded);

      $this->storeCacheEntry($normalized_payload, $model, $decoded, $parsed_output, 'ready');

      $this->loggerFactory->get('dungeoncrawler_content')->notice('Vertex image generation live request completed.', [
        'request_id' => $request_id,
        'http_status' => $response->getStatusCode(),
        'has_image' => $parsed_output['image_data_uri'] !== NULL || $parsed_output['image_url'] !== NULL,
      ]);

      return [
        'success' => TRUE,
        'provider' => 'vertex',
        'provider_model' => $model,
        'mode' => 'live',
        'request_id' => $request_id,
        'status' => 'completed',
        'message' => 'Vertex API request completed.',
        'payload' => $normalized_payload,
        'output' => $parsed_output,
      ];
    }
    catch (GuzzleException | \RuntimeException $exception) {
      $this->loggerFactory->get('dungeoncrawler_content')->error('Vertex image generation request failed.', [
        'request_id' => $request_id,
        'message' => $exception->getMessage(),
      ]);

      $this->storeCacheEntry($normalized_payload, $model, [
        'error' => $exception->getMessage(),
      ], NULL, 'failed');

      return [
        'success' => FALSE,
        'provider' => 'vertex',
        'provider_model' => $model,
        'mode' => 'live',
        'request_id' => $request_id,
        'status' => 'failed',
        'message' => 'Vertex request failed: ' . $exception->getMessage(),
        'payload' => $normalized_payload,
      ];
    }
  }

  /**
   * Attempt to load a cached result before calling the provider.
   */
  private function loadCachedResult(array $normalized_payload, string $model): ?array {
    if (!$this->hasPromptCacheTable()) {
      return NULL;
    }

    $prompt_hash = $this->buildPromptHash($normalized_payload, $model);

    $record = $this->database->select($this->getPromptCacheTable(), 'c')
      ->fields('c')
      ->condition('provider', 'vertex')
      ->condition('provider_model', $model)
      ->condition('prompt_hash', $prompt_hash)
      ->condition('status', 'ready')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($record)) {
      return NULL;
    }

    $output_payload = [];
    if (!empty($record['output_payload']) && is_string($record['output_payload'])) {
      $decoded = json_decode($record['output_payload'], TRUE);
      if (is_array($decoded)) {
        $output_payload = $decoded;
      }
    }

    if (empty($output_payload)) {
      return NULL;
    }

    $this->database->update($this->getPromptCacheTable())
      ->fields([
        'hits' => ((int) ($record['hits'] ?? 0)) + 1,
        'updated' => $this->time->getCurrentTime(),
      ])
      ->condition('id', (int) $record['id'])
      ->execute();

    return [
      'success' => TRUE,
      'provider' => 'vertex',
      'provider_model' => $model,
      'mode' => 'cache',
      'request_id' => 'vertex-cache-' . (string) $record['id'],
      'status' => 'cached',
      'message' => 'Vertex cache hit.',
      'payload' => $normalized_payload,
      'output' => $output_payload,
      'cache' => [
        'cache_id' => (int) $record['id'],
        'prompt_hash' => $prompt_hash,
      ],
    ];
  }

  /**
   * Store a cache entry for the prompt and response.
   */
  private function storeCacheEntry(array $normalized_payload, string $model, array $response_payload, ?array $output_payload, string $status): void {
    if (!$this->hasPromptCacheTable()) {
      return;
    }

    $prompt_hash = $this->buildPromptHash($normalized_payload, $model);
    $now = $this->time->getCurrentTime();

    $fields = [
      'provider' => 'vertex',
      'provider_model' => $model,
      'prompt_hash' => $prompt_hash,
      'prompt_text' => $normalized_payload['prompt'],
      'negative_prompt' => $normalized_payload['negative_prompt'],
      'style' => $normalized_payload['style'],
      'aspect_ratio' => $normalized_payload['aspect_ratio'],
      'status' => $status,
      'request_payload' => json_encode($normalized_payload, JSON_UNESCAPED_UNICODE),
      'response_payload' => json_encode($response_payload, JSON_UNESCAPED_UNICODE),
      'output_payload' => $output_payload !== NULL ? json_encode($output_payload, JSON_UNESCAPED_UNICODE) : NULL,
      'campaign_id' => $normalized_payload['campaign_id'],
      'map_id' => $this->normalizeString($normalized_payload['map_id'] ?? ''),
      'dungeon_id' => $this->normalizeString($normalized_payload['dungeon_id'] ?? ''),
      'room_id' => $this->normalizeString($normalized_payload['room_id'] ?? ''),
      'hex_q' => $normalized_payload['hex_q'],
      'hex_r' => $normalized_payload['hex_r'],
      'entity_type' => $this->normalizeString($normalized_payload['entity_type'] ?? ''),
      'terrain_type' => $this->normalizeString($normalized_payload['terrain_type'] ?? ''),
      'habitat_name' => $this->normalizeString($normalized_payload['habitat_name'] ?? ''),
      'updated' => $now,
    ];

    $existing_id = $this->database->select($this->getPromptCacheTable(), 'c')
      ->fields('c', ['id'])
      ->condition('provider', 'vertex')
      ->condition('provider_model', $model)
      ->condition('prompt_hash', $prompt_hash)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($existing_id) {
      $this->database->update($this->getPromptCacheTable())
        ->fields($fields)
        ->condition('id', (int) $existing_id)
        ->execute();
      return;
    }

    $fields['created'] = $now;
    $this->database->insert($this->getPromptCacheTable())
      ->fields($fields)
      ->execute();
  }

  /**
   * Build a deterministic prompt hash for cache lookup.
   */
  private function buildPromptHash(array $normalized_payload, string $model): string {
    $hash_payload = [
      'prompt' => $normalized_payload['prompt'],
      'negative_prompt' => $normalized_payload['negative_prompt'],
      'style' => $normalized_payload['style'],
      'aspect_ratio' => $normalized_payload['aspect_ratio'],
      'model' => $model,
    ];

    return hash('sha256', json_encode($hash_payload, JSON_UNESCAPED_UNICODE));
  }

  /**
   * Check if the prompt cache table exists.
   */
  private function hasPromptCacheTable(): bool {
    return $this->database->schema()->tableExists($this->getPromptCacheTable());
  }

  /**
   * Get the prompt cache table name.
   */
  private function getPromptCacheTable(): string {
    return 'dungeoncrawler_content_image_prompt_cache';
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
    $configured_key = trim((string) $config->get('vertex_image_api_key'));
    if ($configured_key !== '') {
      return $configured_key;
    }

    $env_key = getenv('VERTEX_API_KEY');
    if (is_string($env_key)) {
      return trim($env_key);
    }

    return '';
  }

  /**
   * Resolve configured project id.
   */
  private function resolveProjectId(ImmutableConfig $config): string {
    return trim((string) $config->get('vertex_image_project_id'));
  }

  /**
   * Resolve configured location.
   */
  private function resolveLocation(ImmutableConfig $config): string {
    $location = trim((string) $config->get('vertex_image_location'));
    return $location !== '' ? $location : 'us-central1';
  }

  /**
   * Resolve configured model name.
   */
  private function resolveModel(ImmutableConfig $config): string {
    $model = trim((string) $config->get('vertex_image_model'));
    return $model !== '' ? $model : 'imagen-3.0-generate-002';
  }

  /**
   * Resolve configured endpoint template.
   */
  private function resolveEndpointTemplate(ImmutableConfig $config): string {
    $endpoint = trim((string) $config->get('vertex_image_endpoint'));
    return $endpoint !== ''
      ? $endpoint
      : 'https://{location}-aiplatform.googleapis.com/v1/projects/{project_id}/locations/{location}/publishers/google/models/{model}:predict';
  }

  /**
   * Resolve configured request timeout.
   */
  private function resolveTimeout(ImmutableConfig $config): int {
    $timeout = (int) $config->get('vertex_image_timeout');
    return $timeout >= 5 ? $timeout : 30;
  }

  /**
   * Build endpoint URL with location, project, model and API key.
   */
  private function buildEndpoint(string $template, string $project_id, string $location, string $model, string $api_key): string {
    $endpoint = str_replace('{project_id}', rawurlencode($project_id), $template);
    $endpoint = str_replace('{location}', rawurlencode($location), $endpoint);
    $endpoint = str_replace('{model}', rawurlencode($model), $endpoint);

    if (strpos($endpoint, 'key=') === FALSE) {
      $separator = strpos($endpoint, '?') === FALSE ? '?' : '&';
      $endpoint .= $separator . 'key=' . rawurlencode($api_key);
    }

    return $endpoint;
  }

  /**
   * Build Vertex request body from normalized payload.
   */
  private function buildVertexRequestBody(array $normalized_payload): array {
    return [
      'instances' => [
        [
          'prompt' => $normalized_payload['prompt'],
        ],
      ],
      'parameters' => [
        'sampleCount' => 1,
        'aspectRatio' => $normalized_payload['aspect_ratio'],
        'style' => $normalized_payload['style'],
        'negativePrompt' => $normalized_payload['negative_prompt'],
      ],
    ];
  }

  /**
   * Normalize a value to a trimmed string.
   */
  private function normalizeString($value): string {
    if (!is_scalar($value)) {
      return '';
    }

    return trim((string) $value);
  }

  /**
   * Normalize a numeric value to int or NULL.
   */
  private function normalizeInt($value): ?int {
    if ($value === NULL || $value === '') {
      return NULL;
    }

    if (is_numeric($value)) {
      return (int) $value;
    }

    return NULL;
  }

  /**
   * Extract text/image output from Vertex response payload.
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

    $predictions = $response['predictions'] ?? [];
    if (!is_array($predictions)) {
      return $output;
    }

    foreach ($predictions as $prediction) {
      if (!is_array($prediction)) {
        continue;
      }

      if ($output['image_data_uri'] === NULL && !empty($prediction['bytesBase64Encoded']) && is_string($prediction['bytesBase64Encoded'])) {
        $output['image_data_uri'] = 'data:image/png;base64,' . $prediction['bytesBase64Encoded'];
      }

      if ($output['image_url'] === NULL && !empty($prediction['imageUri']) && is_string($prediction['imageUri'])) {
        $output['image_url'] = $prediction['imageUri'];
      }

      if ($output['text'] === NULL && !empty($prediction['text']) && is_string($prediction['text'])) {
        $output['text'] = $prediction['text'];
      }
    }

    return $output;
  }

}
