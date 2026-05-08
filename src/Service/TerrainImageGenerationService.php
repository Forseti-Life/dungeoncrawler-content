<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Generates terrain and habitat images using the image generation integration.
 */
class TerrainImageGenerationService {

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
  protected TerrainImagePromptBuilder $promptBuilder;

  /**
   * Module extension list.
   */
  protected ModuleExtensionList $moduleExtensionList;

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
    TerrainImagePromptBuilder $prompt_builder,
    LoggerChannelFactoryInterface $logger_factory,
    ModuleExtensionList $module_extension_list
  ) {
    $this->integrationService = $integration_service;
    $this->generatedImageRepository = $generated_image_repository;
    $this->promptBuilder = $prompt_builder;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * Generates a terrain or habitat image from attribute payloads.
   *
   * @param array $attributes
   *   Terrain generation attributes.
   * @param array $options
   *   Overrides (provider, persist, style, aspect_ratio, negative_prompt).
   *
   * @return array
   *   Generation summary, including raw provider result and storage info.
   */
  public function generateTerrainImage(array $attributes, array $options = []): array {
    // Repository-first: check if a matching terrain image already exists.
    $linkContext = $this->resolveLinkContext($attributes, $options);
    if (!empty($linkContext['table_name']) && !empty($linkContext['object_id'])) {
      $existing = $this->generatedImageRepository->loadImagesForObject(
        $linkContext['table_name'],
        $linkContext['object_id'],
        $linkContext['campaign_id'] ?? NULL,
        $linkContext['slot'] ?? NULL,
        $linkContext['variant'] ?? NULL
      );
      if (!empty($existing)) {
        $url = $this->generatedImageRepository->resolveClientUrl($existing[0]);
        $this->logger->info('Terrain image already exists for @table/@id (slot=@slot), skipping generation.', [
          '@table' => $linkContext['table_name'],
          '@id' => $linkContext['object_id'],
          '@slot' => $linkContext['slot'] ?? 'floortile',
        ]);
        return [
          'attempted' => FALSE,
          'reason' => 'already_exists',
          'existing' => $existing[0],
          'url' => $url,
        ];
      }
    }

    $provider = $this->stringValue($options['provider'] ?? ($attributes['provider'] ?? 'vertex'));
    $payload = $this->buildPayload($attributes, $options);

    try {
      $result = $this->integrationService->generateImage($payload, $provider !== '' ? $provider : 'vertex');

      $storage = [];
      $persist = $this->normalizeBoolean($options['persist'] ?? ($attributes['persist'] ?? TRUE));
      if ($persist !== FALSE) {
        $storage = $this->generatedImageRepository->persistGeneratedImage($result, $linkContext);
      }

      return [
        'attempted' => TRUE,
        'result' => $result,
        'storage' => $storage,
        'payload' => $payload,
      ];
    }
    catch (\Throwable $e) {
      $this->logger->error('Terrain image generation failed: @message', [
        '@message' => $e->getMessage(),
      ]);

      return [
        'attempted' => TRUE,
        'reason' => 'exception',
      ];
    }
  }

  /**
   * Generate a tileset from a module example file.
   *
   * @param string $filename
   *   Example file name (e.g., goblin-warren-tileset.json).
   * @param array $options
   *   Overrides applied to each tile payload.
   *
   * @return array
   *   Tileset generation summary.
   */
  public function generateTilesetFromExample(string $filename, array $options = []): array {
    $path = $this->moduleExtensionList->getPath('dungeoncrawler_content') . '/config/examples/' . ltrim($filename, '/');
    return $this->generateTilesetFromFile($path, $options);
  }

  /**
   * Generate a tileset from a JSON file definition.
   *
   * @param string $file_path
   *   Full path to tileset JSON.
   * @param array $options
   *   Overrides applied to each tile payload.
   *
   * @return array
   *   Tileset generation summary.
   */
  public function generateTilesetFromFile(string $file_path, array $options = []): array {
    if (!is_file($file_path)) {
      return [
        'attempted' => FALSE,
        'reason' => 'tileset_file_missing',
        'path' => $file_path,
      ];
    }

    $raw = file_get_contents($file_path);
    if ($raw === FALSE) {
      return [
        'attempted' => FALSE,
        'reason' => 'tileset_file_unreadable',
        'path' => $file_path,
      ];
    }

    $decoded = json_decode($raw, TRUE);
    if (!is_array($decoded)) {
      return [
        'attempted' => FALSE,
        'reason' => 'tileset_invalid_json',
        'path' => $file_path,
      ];
    }

    $tiles = $decoded['tiles'] ?? [];
    if (!is_array($tiles) || empty($tiles)) {
      return [
        'attempted' => FALSE,
        'reason' => 'tileset_empty',
        'path' => $file_path,
      ];
    }

    $defaults = is_array($decoded['defaults'] ?? NULL) ? $decoded['defaults'] : [];
    $tileset_id = $this->stringValue($decoded['tileset_id'] ?? 'tileset');
    $summary = [
      'attempted' => TRUE,
      'tileset_id' => $tileset_id,
      'total' => count($tiles),
      'generated' => 0,
      'cached' => 0,
      'failed' => 0,
      'results' => [],
    ];

    foreach ($tiles as $tile) {
      if (!is_array($tile)) {
        $summary['failed']++;
        continue;
      }

      $tile_payload = array_merge($defaults, $tile, $options);
      $tile_payload['campaign_context'] = $this->stringValue($tile_payload['campaign_context'] ?? 'tileset=' . $tileset_id);

      $result = $this->generateTerrainImage($tile_payload, $options);
      $mode = '';
      if (!empty($result['result']) && is_array($result['result'])) {
        $mode = (string) ($result['result']['mode'] ?? '');
      }
      $status = '';
      if (!empty($result['result']) && is_array($result['result'])) {
        $status = (string) ($result['result']['status'] ?? '');
      }
      $summary['results'][] = [
        'tile_id' => $this->stringValue($tile_payload['tile_id'] ?? ''),
        'category' => $this->stringValue($tile_payload['category'] ?? ''),
        'attempted' => $result['attempted'] ?? FALSE,
        'mode' => $mode,
        'status' => $status,
      ];

      if ($mode === 'cache') {
        $summary['cached']++;
      }
      elseif (!empty($result['result']['success'])) {
        $summary['generated']++;
      }
      else {
        $summary['failed']++;
      }
    }

    return $summary;
  }

  /**
   * Build a provider payload from attributes and options.
   */
  private function buildPayload(array $attributes, array $options): array {
    $merged = array_merge($attributes, $options);

    $entity_type = $this->stringValue($merged['entity_type'] ?? $this->resolveDefaultEntityType($merged));
    if ($entity_type === '') {
      $entity_type = 'floortile';
    }

    $prompt_payload = $merged;
    $prompt_payload['entity_type'] = $entity_type;

    $prompt = $this->promptBuilder->buildTerrainPrompt($prompt_payload);

    $campaign_context = $this->stringValue($merged['campaign_context'] ?? '');
    if ($campaign_context === '') {
      $campaign_context = $this->buildCampaignContext($merged);
    }

    $payload = [
      'prompt' => $prompt,
      'style' => $this->stringValue($merged['style'] ?? 'fantasy'),
      'aspect_ratio' => $this->stringValue($merged['aspect_ratio'] ?? '1:1'),
      'negative_prompt' => $this->stringValue($merged['negative_prompt'] ?? $this->promptBuilder->getDefaultNegativePrompt()),
      'campaign_context' => $campaign_context,
      'requested_by_uid' => (int) ($merged['requested_by_uid'] ?? 0),
      'entity_type' => $entity_type,
      'resolution' => is_numeric($merged['resolution'] ?? NULL) ? (int) $merged['resolution'] : NULL,
      'tileable' => $merged['tileable'] ?? NULL,
      'view' => $this->stringValue($merged['view'] ?? ''),
      'background' => $this->stringValue($merged['background'] ?? ''),
      'campaign_id' => is_numeric($merged['campaign_id'] ?? NULL) ? (int) $merged['campaign_id'] : NULL,
      'map_id' => $this->stringValue($merged['map_id'] ?? ''),
      'dungeon_id' => $this->stringValue($merged['dungeon_id'] ?? $this->extractNamedContext($merged, 'dungeon')),
      'room_id' => $this->stringValue($merged['room_id'] ?? $this->extractNamedContext($merged, 'room')),
      'hex_q' => is_numeric($merged['hex_q'] ?? NULL) ? (int) $merged['hex_q'] : NULL,
      'hex_r' => is_numeric($merged['hex_r'] ?? NULL) ? (int) $merged['hex_r'] : NULL,
      'terrain_type' => $this->stringValue($merged['terrain_type'] ?? ''),
      'habitat_name' => $this->stringValue($merged['habitat_name'] ?? $this->extractNamedContext($merged, 'habitat')),
      'tile_id' => $this->stringValue($merged['tile_id'] ?? ''),
      'category' => $this->stringValue($merged['category'] ?? ''),
      'notes' => $this->stringValue($merged['notes'] ?? ''),
    ];

    return $payload;
  }

  /**
   * Build a compact campaign context string for storage metadata.
   */
  private function buildCampaignContext(array $payload): string {
    $parts = [];

    $campaign_id = $payload['campaign_id'] ?? NULL;
    if (is_numeric($campaign_id)) {
      $parts[] = 'campaign_id=' . (int) $campaign_id;
    }

    $dungeon = $this->stringValue($payload['dungeon_name'] ?? $this->extractNamedContext($payload, 'dungeon'));
    if ($dungeon !== '') {
      $parts[] = 'dungeon=' . $dungeon;
    }

    $room = $this->stringValue($payload['room_name'] ?? $this->extractNamedContext($payload, 'room'));
    if ($room !== '') {
      $parts[] = 'room=' . $room;
    }

    $habitat = $this->stringValue($payload['habitat_name'] ?? $this->extractNamedContext($payload, 'habitat'));
    if ($habitat !== '') {
      $parts[] = 'habitat=' . $habitat;
    }

    return implode(', ', $parts);
  }

  /**
   * Resolve link context for generated image storage.
   */
  private function resolveLinkContext(array $attributes, array $options): array {
    $context = $attributes['link_context'] ?? ($options['link_context'] ?? []);
    if (!is_array($context)) {
      $context = [];
    }

    $table_name = $this->stringValue($context['table_name'] ?? ($attributes['table_name'] ?? ($options['table_name'] ?? '')));
    $object_id = $this->stringValue($context['object_id'] ?? ($attributes['object_id'] ?? ($options['object_id'] ?? '')));

    if ($table_name === '' || $object_id === '') {
      return [];
    }

    $campaign_id = $context['campaign_id'] ?? ($attributes['campaign_id'] ?? ($options['campaign_id'] ?? NULL));
    if ($campaign_id !== NULL && $campaign_id !== '') {
      $campaign_id = (int) $campaign_id;
    }
    else {
      $campaign_id = NULL;
    }

    $entity_type = $this->stringValue($context['entity_type'] ?? ($attributes['entity_type'] ?? ($options['entity_type'] ?? '')));
    $slot = $this->stringValue($context['slot'] ?? ($attributes['slot'] ?? ($options['slot'] ?? $entity_type)));
    if ($slot === '') {
      $slot = 'floortile';
    }

    $visibility = $this->stringValue($context['visibility'] ?? ($attributes['visibility'] ?? ($options['visibility'] ?? '')));
    if ($visibility === '') {
      $visibility = $campaign_id !== NULL ? 'campaign_party' : 'owner';
    }

    return [
      'owner_uid' => (int) ($context['owner_uid'] ?? ($attributes['requested_by_uid'] ?? ($options['requested_by_uid'] ?? 0))),
      'scope_type' => $this->stringValue($context['scope_type'] ?? ($campaign_id !== NULL ? 'campaign' : 'template')),
      'campaign_id' => $campaign_id,
      'table_name' => $table_name,
      'object_id' => $object_id,
      'slot' => $slot,
      'variant' => $this->stringValue($context['variant'] ?? 'original'),
      'visibility' => $visibility,
      'is_primary' => isset($context['is_primary']) ? (int) (!empty($context['is_primary'])) : 1,
    ];
  }

  /**
   * Resolve default entity type from payload.
   */
  private function resolveDefaultEntityType(array $payload): string {
    $purpose = $this->stringValue($payload['purpose'] ?? '');
    if ($purpose !== '' && strpos($purpose, 'habitat') !== FALSE) {
      return 'background';
    }
    if ($purpose !== '' && strpos($purpose, 'creature') !== FALSE) {
      return 'background';
    }

    return 'floortile';
  }

  /**
   * Safely extract a named context from nested payloads.
   */
  private function extractNamedContext(array $payload, string $key): string {
    if (!array_key_exists($key, $payload)) {
      return '';
    }

    $value = $payload[$key];
    if (is_array($value)) {
      return $this->stringValue($value['name'] ?? '');
    }

    return $this->stringValue($value);
  }

  /**
   * Normalizes a boolean-like value.
   *
   * @return bool|null
   *   TRUE/FALSE when recognizable, NULL otherwise.
   */
  private function normalizeBoolean($value): ?bool {
    if (is_bool($value)) {
      return $value;
    }

    if (is_numeric($value)) {
      return ((int) $value) === 1;
    }

    if (is_string($value)) {
      $normalized = strtolower(trim($value));
      if (in_array($normalized, ['1', 'true', 'yes', 'on'], TRUE)) {
        return TRUE;
      }
      if (in_array($normalized, ['0', 'false', 'no', 'off'], TRUE)) {
        return FALSE;
      }
    }

    return NULL;
  }

  /**
   * Normalizes a value to a trimmed string.
   */
  private function stringValue($value): string {
    if (!is_scalar($value)) {
      return '';
    }

    return trim((string) $value);
  }

}
