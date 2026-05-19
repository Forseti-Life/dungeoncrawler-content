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
    $credential_state = $this->resolveCredentials($config);
    $project_id = $this->resolveProjectId($config, $credential_state['credentials']);
    $has_credentials = $credential_state['credentials'] !== NULL;

    return [
      'enabled' => (bool) $config->get('vertex_image_enabled'),
      'auth_method' => 'service_account_oauth',
      'has_credentials' => $has_credentials,
      'auth_source' => $credential_state['source'],
      // Backward-compatible alias used by existing callers.
      'has_api_key' => $has_credentials,
      'api_key_source' => $credential_state['source'],
      'project_id' => $project_id,
      'project_id_present' => $project_id !== '',
      'location' => $this->resolveLocation($config),
      'model' => $this->resolveModel($config),
      'endpoint' => $this->resolveEndpointTemplate($config),
      'timeout' => $this->resolveTimeout($config),
      'credentials_error' => $credential_state['error'],
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

    if (!$status['enabled'] || !$status['has_credentials']) {
      $mode = !$status['enabled'] ? 'stub' : 'stub_missing_credentials';
      $message = !$status['enabled']
        ? 'Stub accepted. External Vertex API call is not enabled in settings.'
        : 'Stub accepted. Vertex live mode enabled but no service-account credentials were found.';

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
    $credential_state = $this->resolveCredentials($config);
    $credentials = $credential_state['credentials'];
    if ($credentials === NULL) {
      return [
        'success' => FALSE,
        'provider' => 'vertex',
        'provider_model' => $this->resolveModel($config),
        'mode' => 'live',
        'request_id' => $request_id,
        'status' => 'failed',
        'message' => 'Vertex request failed: service-account credentials are unavailable.',
        'payload' => $normalized_payload,
      ];
    }

    $project_id = $this->resolveProjectId($config, $credentials);
    $location = $this->resolveLocation($config);
    $model = $this->resolveModel($config);
    if ($project_id === '') {
      return [
        'success' => FALSE,
        'provider' => 'vertex',
        'provider_model' => $model,
        'mode' => 'live',
        'request_id' => $request_id,
        'status' => 'failed',
        'message' => 'Vertex request failed: project ID is not configured.',
        'payload' => $normalized_payload,
      ];
    }

    $cached = $this->loadCachedResult($normalized_payload, $model);
    if ($cached !== NULL) {
      return $cached;
    }

    $endpoint = $this->buildEndpoint($this->resolveEndpointTemplate($config), $project_id, $location, $model);
    $timeout = $this->resolveTimeout($config);
    $request_body = $this->buildVertexRequestBody($normalized_payload);

    try {
      $access_token = $this->fetchAccessToken($credentials, $timeout);
      $response = $this->httpClient->request('POST', $endpoint, [
        'headers' => [
          'Accept' => 'application/json',
          'Authorization' => 'Bearer ' . $access_token,
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

    $entity_type = (string) ($normalized_payload['entity_type'] ?? '');
    if ($entity_type === 'room_view') {
      $hash_payload['room_id'] = $normalized_payload['room_id'] ?? '';
    }
    elseif ($entity_type === 'room_view_gallery') {
      $hash_payload['campaign_id'] = $normalized_payload['campaign_id'] ?? NULL;
      $hash_payload['dungeon_id'] = $normalized_payload['dungeon_id'] ?? '';
      $hash_payload['room_id'] = $normalized_payload['room_id'] ?? '';
      $hash_payload['scene_index'] = $normalized_payload['scene_index'] ?? '';
    }

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
   * Resolve service-account credentials from config or environment.
   */
  private function resolveCredentials(ImmutableConfig $config): array {
    $configured_json = trim((string) $config->get('vertex_service_account_json'));
    if ($configured_json !== '') {
      $credentials = $this->decodeServiceAccountJson($configured_json);
      return [
        'credentials' => $credentials,
        'source' => $credentials !== NULL ? 'config_json' : 'invalid_config_json',
        'error' => $credentials !== NULL ? NULL : 'Configured Vertex service account JSON is invalid.',
      ];
    }

    $configured_file = trim((string) $config->get('vertex_service_account_file'));
    if ($configured_file !== '') {
      $credentials = $this->loadServiceAccountFile($configured_file);
      return [
        'credentials' => $credentials,
        'source' => $credentials !== NULL ? 'config_file' : 'invalid_config_file',
        'error' => $credentials !== NULL ? NULL : 'Configured Vertex service account file is missing or invalid.',
      ];
    }

    $env_json = getenv('VERTEX_SERVICE_ACCOUNT_JSON');
    if (is_string($env_json) && trim($env_json) !== '') {
      $credentials = $this->decodeServiceAccountJson($env_json);
      return [
        'credentials' => $credentials,
        'source' => $credentials !== NULL ? 'env_json' : 'invalid_env_json',
        'error' => $credentials !== NULL ? NULL : 'VERTEX_SERVICE_ACCOUNT_JSON is invalid.',
      ];
    }

    $env_json_base64 = getenv('VERTEX_SERVICE_ACCOUNT_JSON_BASE64');
    if (is_string($env_json_base64) && trim($env_json_base64) !== '') {
      $decoded_json = base64_decode(trim($env_json_base64), TRUE);
      $credentials = is_string($decoded_json) ? $this->decodeServiceAccountJson($decoded_json) : NULL;
      return [
        'credentials' => $credentials,
        'source' => $credentials !== NULL ? 'env_json_base64' : 'invalid_env_json_base64',
        'error' => $credentials !== NULL ? NULL : 'VERTEX_SERVICE_ACCOUNT_JSON_BASE64 is invalid.',
      ];
    }

    foreach (['VERTEX_SERVICE_ACCOUNT_FILE', 'GOOGLE_APPLICATION_CREDENTIALS'] as $env_name) {
      $env_file = getenv($env_name);
      if (is_string($env_file) && trim($env_file) !== '') {
        $credentials = $this->loadServiceAccountFile($env_file);
        return [
          'credentials' => $credentials,
          'source' => $credentials !== NULL ? strtolower($env_name) : 'invalid_' . strtolower($env_name),
          'error' => $credentials !== NULL ? NULL : sprintf('%s points to an invalid service account file.', $env_name),
        ];
      }
    }

    return [
      'credentials' => NULL,
      'source' => 'none',
      'error' => NULL,
    ];
  }

  /**
   * Resolve configured project id.
   */
  private function resolveProjectId(ImmutableConfig $config, ?array $credentials = NULL): string {
    $configured = trim((string) $config->get('vertex_image_project_id'));
    if ($configured !== '') {
      return $configured;
    }

    $credential_project_id = is_array($credentials) ? trim((string) ($credentials['project_id'] ?? '')) : '';
    return $credential_project_id;
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
   * Build endpoint URL with location, project, and model.
   */
  private function buildEndpoint(string $template, string $project_id, string $location, string $model): string {
    $endpoint = str_replace('{project_id}', rawurlencode($project_id), $template);
    $endpoint = str_replace('{location}', rawurlencode($location), $endpoint);
    $endpoint = str_replace('{model}', rawurlencode($model), $endpoint);

    return $endpoint;
  }

  /**
   * Fetch an OAuth2 access token for Vertex service-account requests.
   */
  private function fetchAccessToken(array $credentials, int $timeout): string {
    $token_uri = trim((string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token'));
    $jwt = $this->buildSignedJwt($credentials, $token_uri);

    $response = $this->httpClient->request('POST', $token_uri, [
      'form_params' => [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
      ],
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'timeout' => $timeout,
    ]);

    $decoded = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($decoded) || empty($decoded['access_token']) || !is_string($decoded['access_token'])) {
      throw new \RuntimeException('Vertex OAuth token response did not include an access_token.');
    }

    return $decoded['access_token'];
  }

  /**
   * Build a signed JWT assertion for OAuth service-account exchange.
   */
  private function buildSignedJwt(array $credentials, string $token_uri): string {
    $issued_at = $this->time->getCurrentTime();
    $header = $this->base64UrlEncode(json_encode([
      'alg' => 'RS256',
      'typ' => 'JWT',
    ], JSON_UNESCAPED_SLASHES));
    $claims = $this->base64UrlEncode(json_encode([
      'iss' => $credentials['client_email'],
      'scope' => 'https://www.googleapis.com/auth/cloud-platform',
      'aud' => $token_uri,
      'exp' => $issued_at + 3600,
      'iat' => $issued_at,
    ], JSON_UNESCAPED_SLASHES));

    $signing_input = $header . '.' . $claims;
    $private_key = openssl_pkey_get_private((string) $credentials['private_key']);
    if ($private_key === FALSE) {
      throw new \RuntimeException('Vertex service account private key could not be loaded.');
    }

    $signature = '';
    $signed = openssl_sign($signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256);
    if (is_resource($private_key) || $private_key instanceof \OpenSSLAsymmetricKey) {
      openssl_free_key($private_key);
    }
    if (!$signed) {
      throw new \RuntimeException('Vertex OAuth JWT signing failed.');
    }

    return $signing_input . '.' . $this->base64UrlEncode($signature);
  }

  /**
   * Decode a service-account JSON payload.
   */
  private function decodeServiceAccountJson(string $json): ?array {
    $decoded = json_decode($json, TRUE);
    if (!is_array($decoded)) {
      return NULL;
    }

    $client_email = trim((string) ($decoded['client_email'] ?? ''));
    $private_key = (string) ($decoded['private_key'] ?? '');
    if ($client_email === '' || $private_key === '') {
      return NULL;
    }

    return [
      'client_email' => $client_email,
      'private_key' => $private_key,
      'project_id' => trim((string) ($decoded['project_id'] ?? '')),
      'token_uri' => trim((string) ($decoded['token_uri'] ?? 'https://oauth2.googleapis.com/token')),
    ];
  }

  /**
   * Load service-account credentials from a local file.
   */
  private function loadServiceAccountFile(string $path): ?array {
    $resolved_path = trim($path);
    if ($resolved_path === '' || !is_readable($resolved_path)) {
      return NULL;
    }

    $contents = file_get_contents($resolved_path);
    if (!is_string($contents) || $contents === '') {
      return NULL;
    }

    return $this->decodeServiceAccountJson($contents);
  }

  /**
   * Base64-url encode JWT parts.
   */
  private function base64UrlEncode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
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
