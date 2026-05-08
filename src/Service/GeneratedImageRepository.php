<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Uuid\UuidInterface;

/**
 * Repository for generated image records and object links.
 */
class GeneratedImageRepository {

  /**
   * Database connection.
   */
  protected Connection $database;

  /**
   * File URL generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * File system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * UUID service.
   */
  protected UuidInterface $uuid;

  /**
   * Time service.
   */
  protected TimeInterface $time;

  /**
   * Logger channel.
   */
  protected $logger;

  /**
   * Constructs the repository.
   */
  public function __construct(Connection $database, FileUrlGeneratorInterface $file_url_generator, FileSystemInterface $file_system, UuidInterface $uuid, TimeInterface $time, LoggerChannelFactoryInterface $logger_factory) {
    $this->database = $database;
    $this->fileUrlGenerator = $file_url_generator;
    $this->fileSystem = $file_system;
    $this->uuid = $uuid;
    $this->time = $time;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * Persists a generated image and optional object link.
   *
   * @return array<string, mixed>
   *   Persistence summary.
   */
  public function persistGeneratedImage(array $generation_result, array $link_context = []): array {
    if (!$this->database->schema()->tableExists('dc_generated_images') || !$this->database->schema()->tableExists('dc_generated_image_links')) {
      return [
        'stored' => FALSE,
        'reason' => 'tables_missing',
      ];
    }

    if (empty($generation_result['success'])) {
      return [
        'stored' => FALSE,
        'reason' => 'generation_failed',
      ];
    }

    $output = $generation_result['output'] ?? [];
    $image_data_uri = is_array($output) && isset($output['image_data_uri']) && is_string($output['image_data_uri']) ? $output['image_data_uri'] : '';
    $image_url = is_array($output) && isset($output['image_url']) && is_string($output['image_url']) ? $output['image_url'] : '';

    if ($image_data_uri === '' && $image_url === '') {
      return [
        'stored' => FALSE,
        'reason' => 'no_image_output',
      ];
    }

    $image_uuid = $this->uuid->generate();
    $now = $this->time->getCurrentTime();

    $resolved = $this->resolveStorageFromOutput($image_uuid, $image_data_uri, $image_url);
    if (empty($resolved['ok'])) {
      return [
        'stored' => FALSE,
        'reason' => $resolved['reason'] ?? 'storage_failed',
      ];
    }

    $owner_uid = (int) ($generation_result['payload']['requested_by_uid'] ?? ($link_context['owner_uid'] ?? 0));

    $image_id = (int) $this->database->insert('dc_generated_images')
      ->fields([
        'image_uuid' => $image_uuid,
        'owner_uid' => $owner_uid,
        'provider' => (string) ($generation_result['provider'] ?? 'gemini'),
        'provider_request_id' => (string) ($generation_result['request_id'] ?? ''),
        'provider_model' => (string) ($generation_result['provider_model'] ?? ''),
        'status' => 'ready',
        'mime_type' => (string) ($resolved['mime_type'] ?? ''),
        'width' => isset($resolved['width']) ? (int) $resolved['width'] : NULL,
        'height' => isset($resolved['height']) ? (int) $resolved['height'] : NULL,
        'bytes' => isset($resolved['bytes']) ? (int) $resolved['bytes'] : NULL,
        'storage_scheme' => (string) ($resolved['storage_scheme'] ?? 'public'),
        'file_uri' => (string) ($resolved['file_uri'] ?? ''),
        'public_url' => $resolved['public_url'] ?? NULL,
        'sha256' => $resolved['sha256'] ?? NULL,
        'prompt_text' => (string) ($generation_result['payload']['prompt'] ?? ''),
        'negative_prompt' => (string) ($generation_result['payload']['negative_prompt'] ?? ''),
        'generation_params' => json_encode([
          'style' => $generation_result['payload']['style'] ?? NULL,
          'aspect_ratio' => $generation_result['payload']['aspect_ratio'] ?? NULL,
          'campaign_context' => $generation_result['payload']['campaign_context'] ?? NULL,
          'mode' => $generation_result['mode'] ?? NULL,
        ], JSON_UNESCAPED_UNICODE),
        'safety_metadata' => NULL,
        'created' => $now,
        'updated' => $now,
        'deleted' => 0,
      ])
      ->execute();

    $link_table = isset($link_context['table_name']) && is_string($link_context['table_name']) ? trim($link_context['table_name']) : '';
    $link_object = isset($link_context['object_id']) && is_scalar($link_context['object_id']) ? trim((string) $link_context['object_id']) : '';

    if ($link_table !== '' && $link_object !== '') {
      $scope_type = isset($link_context['scope_type']) && is_string($link_context['scope_type']) ? $link_context['scope_type'] : 'campaign';
      $campaign_id = isset($link_context['campaign_id']) && $link_context['campaign_id'] !== '' ? (int) $link_context['campaign_id'] : NULL;
      $slot = isset($link_context['slot']) && is_string($link_context['slot']) ? $link_context['slot'] : 'portrait';
      $variant = isset($link_context['variant']) && is_string($link_context['variant']) ? $link_context['variant'] : 'original';
      $visibility = isset($link_context['visibility']) && is_string($link_context['visibility']) ? $link_context['visibility'] : 'owner';
      $is_primary = isset($link_context['is_primary']) ? (int) (!empty($link_context['is_primary'])) : 1;

      $this->database->insert('dc_generated_image_links')
        ->fields([
          'image_id' => $image_id,
          'scope_type' => $scope_type,
          'campaign_id' => $campaign_id,
          'table_name' => $link_table,
          'object_id' => $link_object,
          'slot' => $slot,
          'variant' => $variant,
          'is_primary' => $is_primary,
          'sort_weight' => 0,
          'visibility' => $visibility,
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
    }

    return [
      'stored' => TRUE,
      'image_id' => $image_id,
      'image_uuid' => $image_uuid,
      'url' => $this->resolveClientUrl([
        'public_url' => $resolved['public_url'] ?? NULL,
        'file_uri' => $resolved['file_uri'] ?? '',
      ]),
    ];
  }

  /**
   * Loads an image by UUID.
   */
  public function loadImageByUuid(string $image_uuid): ?array {
    $row = $this->database->select('dc_generated_images', 'i')
      ->fields('i')
      ->condition('image_uuid', $image_uuid)
      ->condition('deleted', 0)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return is_array($row) ? $row : NULL;
  }

  /**
   * Loads all links for an image id.
   *
   * @return array<int, array<string, mixed>>
   *   Image links.
   */
  public function loadLinksForImageId(int $image_id): array {
    $rows = $this->database->select('dc_generated_image_links', 'l')
      ->fields('l')
      ->condition('image_id', $image_id)
      ->orderBy('is_primary', 'DESC')
      ->orderBy('sort_weight', 'ASC')
      ->orderBy('created', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
  }

  /**
   * Loads images attached to an object.
   *
   * @return array<int, array<string, mixed>>
   *   Image rows with link metadata.
   */
  public function loadImagesForObject(string $table_name, string $object_id, ?int $campaign_id = NULL, ?string $slot = NULL, ?string $variant = NULL): array {
    $query = $this->database->select('dc_generated_image_links', 'l');
    $query->fields('l');
    $query->condition('l.table_name', $table_name);
    $query->condition('l.object_id', $object_id);

    if ($campaign_id !== NULL) {
      $query->condition('l.campaign_id', $campaign_id);
    }
    if ($slot !== NULL && $slot !== '') {
      $query->condition('l.slot', $slot);
    }
    if ($variant !== NULL && $variant !== '') {
      $query->condition('l.variant', $variant);
    }

    $query->leftJoin('dc_generated_images', 'i', 'i.id = l.image_id');
    $query->addField('i', 'image_uuid', 'image_uuid');
    $query->addField('i', 'owner_uid', 'owner_uid');
    $query->addField('i', 'provider', 'provider');
    $query->addField('i', 'provider_model', 'provider_model');
    $query->addField('i', 'status', 'image_status');
    $query->addField('i', 'mime_type', 'mime_type');
    $query->addField('i', 'width', 'width');
    $query->addField('i', 'height', 'height');
    $query->addField('i', 'bytes', 'bytes');
    $query->addField('i', 'storage_scheme', 'storage_scheme');
    $query->addField('i', 'file_uri', 'file_uri');
    $query->addField('i', 'public_url', 'public_url');
    $query->addField('i', 'created', 'image_created');

    $query->condition('i.deleted', 0);
    $query->condition('i.status', 'ready');
    $query->orderBy('l.is_primary', 'DESC');
    $query->orderBy('l.sort_weight', 'ASC');
    $query->orderBy('l.created', 'DESC');

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
  }

  /**
   * Loads the first matching image for each of multiple objects in one query.
   *
   * @param string $table_name
   *   Linked table name (e.g. 'dc_dungeon_sprites').
   * @param array<int, string> $object_ids
   *   Object IDs to look up.
   * @param int|null $campaign_id
   *   Optional campaign scope.
   * @param string|null $slot
   *   Optional slot filter (e.g. 'sprite', 'portrait').
   * @param string|null $variant
   *   Optional variant filter (e.g. 'original').
   *
   * @return array<string, array<string, mixed>>
   *   Map of object_id => first matching image row (primary, most recent).
   */
  public function loadImagesForObjects(string $table_name, array $object_ids, ?int $campaign_id = NULL, ?string $slot = NULL, ?string $variant = NULL): array {
    $clean_ids = [];
    foreach ($object_ids as $id) {
      $value = trim((string) $id);
      if ($value !== '') {
        $clean_ids[] = $value;
      }
    }

    if (empty($clean_ids)) {
      return [];
    }

    $query = $this->database->select('dc_generated_image_links', 'l');
    $query->fields('l');
    $query->condition('l.table_name', $table_name);
    $query->condition('l.object_id', array_values(array_unique($clean_ids)), 'IN');

    if ($campaign_id !== NULL) {
      $query->condition('l.campaign_id', $campaign_id);
    }
    if ($slot !== NULL && $slot !== '') {
      $query->condition('l.slot', $slot);
    }
    if ($variant !== NULL && $variant !== '') {
      $query->condition('l.variant', $variant);
    }

    $query->leftJoin('dc_generated_images', 'i', 'i.id = l.image_id');
    $query->addField('i', 'image_uuid', 'image_uuid');
    $query->addField('i', 'owner_uid', 'owner_uid');
    $query->addField('i', 'provider', 'provider');
    $query->addField('i', 'provider_model', 'provider_model');
    $query->addField('i', 'status', 'image_status');
    $query->addField('i', 'mime_type', 'mime_type');
    $query->addField('i', 'width', 'width');
    $query->addField('i', 'height', 'height');
    $query->addField('i', 'bytes', 'bytes');
    $query->addField('i', 'storage_scheme', 'storage_scheme');
    $query->addField('i', 'file_uri', 'file_uri');
    $query->addField('i', 'public_url', 'public_url');
    $query->addField('i', 'created', 'image_created');

    $query->condition('i.deleted', 0);
    $query->condition('i.status', 'ready');
    $query->orderBy('l.is_primary', 'DESC');
    $query->orderBy('l.sort_weight', 'ASC');
    $query->orderBy('l.created', 'DESC');

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (!is_array($rows)) {
      return [];
    }

    // Keep only the first (highest priority) row per object_id.
    $results = [];
    foreach ($rows as $row) {
      $oid = (string) ($row['object_id'] ?? '');
      if ($oid !== '' && !isset($results[$oid])) {
        $results[$oid] = $row;
      }
    }

    return $results;
  }

  /**
   * Loads generated image counts per object for a table.
   *
   * @param string $table_name
   *   Linked table name.
   * @param array<int, string> $object_ids
   *   Object IDs to count.
   *
   * @return array<string, int>
   *   Map of object_id => linked image count.
   */
  public function loadImageCountsForObjects(string $table_name, array $object_ids): array {
    $clean_ids = [];
    foreach ($object_ids as $object_id) {
      $value = trim((string) $object_id);
      if ($value !== '') {
        $clean_ids[] = $value;
      }
    }

    if (empty($clean_ids)) {
      return [];
    }

    $query = $this->database->select('dc_generated_image_links', 'l');
    $query->addField('l', 'object_id');
    $query->addExpression('COUNT(l.image_id)', 'image_count');
    $query->condition('l.table_name', $table_name);
    $query->condition('l.object_id', array_values(array_unique($clean_ids)), 'IN');
    $query->groupBy('l.object_id');

    $query->leftJoin('dc_generated_images', 'i', 'i.id = l.image_id');
    $query->condition('i.deleted', 0);
    $query->condition('i.status', 'ready');

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (!is_array($rows)) {
      return [];
    }

    $counts = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }

      $object_id = isset($row['object_id']) ? (string) $row['object_id'] : '';
      if ($object_id === '') {
        continue;
      }

      $counts[$object_id] = isset($row['image_count']) ? (int) $row['image_count'] : 0;
    }

    return $counts;
  }

  /**
   * Loads campaign images with optional object filters.
   *
   * @return array<int, array<string, mixed>>
   *   Image rows with link metadata.
   */
  public function loadCampaignImages(int $campaign_id, ?string $table_name = NULL, ?string $object_id = NULL, ?string $slot = NULL): array {
    $query = $this->database->select('dc_generated_image_links', 'l');
    $query->fields('l');
    $query->condition('l.campaign_id', $campaign_id);

    if ($table_name !== NULL && $table_name !== '') {
      $query->condition('l.table_name', $table_name);
    }
    if ($object_id !== NULL && $object_id !== '') {
      $query->condition('l.object_id', $object_id);
    }
    if ($slot !== NULL && $slot !== '') {
      $query->condition('l.slot', $slot);
    }

    $query->leftJoin('dc_generated_images', 'i', 'i.id = l.image_id');
    $query->addField('i', 'image_uuid', 'image_uuid');
    $query->addField('i', 'owner_uid', 'owner_uid');
    $query->addField('i', 'provider', 'provider');
    $query->addField('i', 'provider_model', 'provider_model');
    $query->addField('i', 'status', 'image_status');
    $query->addField('i', 'mime_type', 'mime_type');
    $query->addField('i', 'width', 'width');
    $query->addField('i', 'height', 'height');
    $query->addField('i', 'bytes', 'bytes');
    $query->addField('i', 'storage_scheme', 'storage_scheme');
    $query->addField('i', 'file_uri', 'file_uri');
    $query->addField('i', 'public_url', 'public_url');
    $query->addField('i', 'created', 'image_created');

    $query->condition('i.deleted', 0);
    $query->condition('i.status', 'ready');
    $query->orderBy('l.table_name', 'ASC');
    $query->orderBy('l.object_id', 'ASC');
    $query->orderBy('l.slot', 'ASC');
    $query->orderBy('l.is_primary', 'DESC');
    $query->orderBy('l.sort_weight', 'ASC');

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
  }

  /**
   * Resolves the best client URL for an image row.
   */
  public function resolveClientUrl(array $image_row): ?string {
    if (!empty($image_row['public_url']) && is_string($image_row['public_url'])) {
      return $image_row['public_url'];
    }

    $file_uri = $image_row['file_uri'] ?? NULL;
    if (!is_string($file_uri) || $file_uri === '') {
      return NULL;
    }

    if (str_starts_with($file_uri, 'http://') || str_starts_with($file_uri, 'https://')) {
      return $file_uri;
    }

    // Avoid exposing temporary stream URLs directly to browsers. They often
    // resolve to /system/temporary routes that can 403 depending on session
    // and access context, causing noisy broken image requests in the UI.
    if (str_starts_with($file_uri, 'temporary://')) {
      return NULL;
    }

    try {
      $relative = $this->fileUrlGenerator->generateString($file_uri);
      if (is_string($relative) && $relative !== '') {
        return $relative;
      }

      $absolute = $this->fileUrlGenerator->generateAbsoluteString($file_uri);
      if (!is_string($absolute) || $absolute === '') {
        return NULL;
      }

      $parts = parse_url($absolute);
      if (($parts['host'] ?? '') === 'default') {
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        return $path !== '' ? $path . $query . $fragment : NULL;
      }

      return $absolute;
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  /**
   * Resolves and stores image output from generation response.
   *
   * @return array<string, mixed>
   *   Storage resolution data.
   */
  private function resolveStorageFromOutput(string $image_uuid, string $image_data_uri, string $image_url): array {
    if ($image_data_uri !== '') {
      $matches = [];
      if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/s', $image_data_uri, $matches)) {
        return ['ok' => FALSE, 'reason' => 'invalid_data_uri'];
      }

      $mime_type = $matches[1];
      $binary = base64_decode($matches[2], TRUE);
      if ($binary === FALSE) {
        return ['ok' => FALSE, 'reason' => 'invalid_base64'];
      }

      $extension = $this->extensionFromMime($mime_type);
      $dimensions = @getimagesizefromstring($binary) ?: [NULL, NULL];

      $base_directory = 'public://generated-images';
      if (!$this->fileSystem->prepareDirectory($base_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        $this->logger->error('Generated image persistence failed: public generated-images directory unavailable.');
        return ['ok' => FALSE, 'reason' => 'public_directory_unavailable'];
      }

      $year_directory = $base_directory . '/' . date('Y', $this->time->getCurrentTime());
      if (!$this->fileSystem->prepareDirectory($year_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        $this->logger->error('Generated image persistence failed: year directory unavailable (@dir).', ['@dir' => $year_directory]);
        return ['ok' => FALSE, 'reason' => 'public_directory_unavailable'];
      }

      $directory = $year_directory . '/' . date('m', $this->time->getCurrentTime());
      if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        $this->logger->error('Generated image persistence failed: month directory unavailable (@dir).', ['@dir' => $directory]);
        return ['ok' => FALSE, 'reason' => 'public_directory_unavailable'];
      }

      $destination = $directory . '/' . $image_uuid . '.' . $extension;
      $saved_uri = $this->fileSystem->saveData($binary, $destination, FileSystemInterface::EXISTS_REPLACE);
      if (!is_string($saved_uri) || $saved_uri === '') {
        $this->logger->error('Generated image persistence failed: unable to save image data to destination (@dest).', ['@dest' => $destination]);
        return ['ok' => FALSE, 'reason' => 'public_save_failed'];
      }

      return [
        'ok' => TRUE,
        'storage_scheme' => 'public',
        'file_uri' => $saved_uri,
        'public_url' => NULL,
        'mime_type' => $mime_type,
        'bytes' => strlen($binary),
        'width' => isset($dimensions[0]) ? (int) $dimensions[0] : NULL,
        'height' => isset($dimensions[1]) ? (int) $dimensions[1] : NULL,
        'sha256' => hash('sha256', $binary),
      ];
    }

    if ($image_url !== '') {
      return [
        'ok' => TRUE,
        'storage_scheme' => 'remote',
        'file_uri' => $image_url,
        'public_url' => $image_url,
        'mime_type' => NULL,
        'bytes' => NULL,
        'width' => NULL,
        'height' => NULL,
        'sha256' => NULL,
      ];
    }

    return ['ok' => FALSE, 'reason' => 'empty_output'];
  }

  /**
   * Maps image MIME type to file extension.
   */
  private function extensionFromMime(string $mime_type): string {
    return match ($mime_type) {
      'image/jpeg' => 'jpg',
      'image/webp' => 'webp',
      'image/gif' => 'gif',
      default => 'png',
    };
  }

}
