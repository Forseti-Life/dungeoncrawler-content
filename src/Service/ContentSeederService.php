<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Seeds template libraries and content data from packaged JSON files.
 *
 * All authoritative content is stored in the module's content/ directory as
 * JSON files and imported into the database via this service. This ensures
 * the full content library is reproducible from the repository alone.
 *
 * Content categories:
 * - setting_templates/  → dungeoncrawler_content_setting_templates
 * - room_templates/     → dungeoncrawler_content_room_templates
 * - items/              → dungeoncrawler_content_registry (via ContentRegistry)
 * - creatures/          → dungeoncrawler_content_registry
 * - traps/              → dungeoncrawler_content_registry
 * - hazards/            → dungeoncrawler_content_registry
 * - loot_tables.json    → dungeoncrawler_content_loot_tables
 * - encounter_templates.json → dungeoncrawler_content_encounter_templates
 * - quest_templates.json → dungeoncrawler_content_quest_templates
 * - images/             → dc_generated_images + dc_generated_image_links
 */
class ContentSeederService {

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * The module extension list.
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * The file system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Constructs a ContentSeederService.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    ModuleExtensionList $module_extension_list,
    FileSystemInterface $file_system,
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->moduleExtensionList = $module_extension_list;
    $this->fileSystem = $file_system;
  }

  /**
   * Get the absolute path to the module's content directory.
   */
  protected function getContentPath(): string {
    $module_path = $this->moduleExtensionList->getPath('dungeoncrawler_content');
    return DRUPAL_ROOT . '/' . $module_path . '/content';
  }

  /**
   * Seed all content from packaged JSON files.
   *
   * @param bool $force
   *   If TRUE, overwrite existing records. If FALSE, skip duplicates.
   *
   * @return array
   *   Summary of seeded counts per category.
   */
  public function seedAll(bool $force = FALSE): array {
    $summary = [];

    $summary['setting_templates'] = $this->seedSettingTemplates($force);
    $summary['room_templates'] = $this->seedRoomTemplates($force);
    $summary['loot_tables'] = $this->seedLootTables($force);
    $summary['encounter_templates'] = $this->seedEncounterTemplates($force);
    $summary['quest_templates'] = $this->seedQuestTemplates($force);
    $summary['images'] = $this->seedImageManifest($force);
    $summary['image_files'] = $this->copyImageFiles();
    $summary['prompt_cache'] = $this->seedPromptCache($force);

    $total = array_sum($summary);
    $this->logger->info('Content seeder complete: @total total records across @cats categories.', [
      '@total' => $total,
      '@cats' => count(array_filter($summary)),
    ]);

    return $summary;
  }

  /**
   * Seed setting templates from content/setting_templates/*.json.
   */
  public function seedSettingTemplates(bool $force = FALSE): int {
    $dir = $this->getContentPath() . '/setting_templates';
    if (!is_dir($dir)) {
      return 0;
    }

    $count = 0;
    $files = glob($dir . '/*.json');

    foreach ($files as $file) {
      $data = $this->loadJsonFile($file);
      if (!$data || empty($data['template_id'])) {
        continue;
      }

      $template_id = $data['template_id'];

      // Check if exists.
      $exists = $this->database->select('dungeoncrawler_content_setting_templates', 't')
        ->condition('template_id', $template_id)
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($exists && !$force) {
        continue;
      }

      $now = time();
      $fields = [
        'template_id' => $template_id,
        'name' => $data['name'] ?? 'Unknown',
        'description' => $data['description'] ?? '',
        'setting_type' => $data['setting_type'] ?? 'default',
        'size' => $data['size'] ?? 'medium',
        'lighting' => $data['lighting'] ?? 'normal_light',
        'setting_data' => is_array($data['setting_data'] ?? NULL) ? json_encode($data['setting_data']) : ($data['setting_data'] ?? '{}'),
        'search_tags' => is_array($data['search_tags'] ?? NULL) ? json_encode($data['search_tags']) : ($data['search_tags'] ?? '[]'),
        'level_min' => (int) ($data['level_min'] ?? 1),
        'level_max' => (int) ($data['level_max'] ?? 20),
        'usage_count' => (int) ($data['usage_count'] ?? 0),
        'quality_score' => (float) ($data['quality_score'] ?? 0.5),
        'source' => $data['source'] ?? 'seeded',
        'created' => $now,
        'updated' => $now,
      ];

      try {
        if ($exists && $force) {
          $this->database->delete('dungeoncrawler_content_setting_templates')
            ->condition('template_id', $template_id)
            ->execute();
        }
        $this->database->insert('dungeoncrawler_content_setting_templates')
          ->fields($fields)
          ->execute();
        $count++;
      }
      catch (\Exception $e) {
        $this->logger->warning('Failed to seed setting template @tid: @err', [
          '@tid' => $template_id,
          '@err' => $e->getMessage(),
        ]);
      }
    }

    $this->logger->info('Seeded @count setting templates from @total files.', [
      '@count' => $count,
      '@total' => count($files),
    ]);
    return $count;
  }

  /**
   * Seed room templates from content/room_templates/*.json.
   */
  public function seedRoomTemplates(bool $force = FALSE): int {
    $dir = $this->getContentPath() . '/room_templates';
    if (!is_dir($dir)) {
      return 0;
    }

    $count = 0;
    $files = glob($dir . '/*.json');

    foreach ($files as $file) {
      $data = $this->loadJsonFile($file);
      if (!$data || empty($data['template_id'])) {
        continue;
      }

      $template_id = $data['template_id'];

      $exists = $this->database->select('dungeoncrawler_content_room_templates', 't')
        ->condition('template_id', $template_id)
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($exists && !$force) {
        continue;
      }

      $now = time();
      $fields = [
        'template_id' => $template_id,
        'name' => $data['name'] ?? 'Unknown',
        'description' => $data['description'] ?? '',
        'room_type' => $data['room_type'] ?? 'chamber',
        'size_category' => $data['size_category'] ?? 'medium',
        'theme' => $data['theme'] ?? '',
        'terrain_type' => $data['terrain_type'] ?? 'stone',
        'lighting' => $data['lighting'] ?? 'normal_light',
        'hex_count' => (int) ($data['hex_count'] ?? 0),
        'layout_data' => is_array($data['layout_data'] ?? NULL) ? json_encode($data['layout_data']) : ($data['layout_data'] ?? '{}'),
        'contents_data' => is_array($data['contents_data'] ?? NULL) ? json_encode($data['contents_data']) : ($data['contents_data'] ?? '{}'),
        'environment_tags' => is_array($data['environment_tags'] ?? NULL) ? json_encode($data['environment_tags']) : ($data['environment_tags'] ?? '[]'),
        'search_tags' => is_array($data['search_tags'] ?? NULL) ? json_encode($data['search_tags']) : ($data['search_tags'] ?? '[]'),
        'level_min' => (int) ($data['level_min'] ?? 1),
        'level_max' => (int) ($data['level_max'] ?? 20),
        'difficulty' => $data['difficulty'] ?? 'moderate',
        'usage_count' => (int) ($data['usage_count'] ?? 0),
        'quality_score' => (float) ($data['quality_score'] ?? 0.5),
        'source' => $data['source'] ?? 'seeded',
        'source_campaign_id' => !empty($data['source_campaign_id']) ? (int) $data['source_campaign_id'] : NULL,
        'source_room_id' => $data['source_room_id'] ?? NULL,
        'created' => $now,
        'updated' => $now,
      ];

      try {
        if ($exists && $force) {
          $this->database->delete('dungeoncrawler_content_room_templates')
            ->condition('template_id', $template_id)
            ->execute();
        }
        $this->database->insert('dungeoncrawler_content_room_templates')
          ->fields($fields)
          ->execute();
        $count++;
      }
      catch (\Exception $e) {
        $this->logger->warning('Failed to seed room template @tid: @err', [
          '@tid' => $template_id,
          '@err' => $e->getMessage(),
        ]);
      }
    }

    $this->logger->info('Seeded @count room templates from @total files.', [
      '@count' => $count,
      '@total' => count($files),
    ]);
    return $count;
  }

  /**
   * Seed loot tables from content/loot_tables.json.
   *
   * Schema: table_id, name, description, level_range, entries, created.
   */
  public function seedLootTables(bool $force = FALSE): int {
    $file = $this->getContentPath() . '/loot_tables.json';
    if (!file_exists($file)) {
      return 0;
    }

    $rows = $this->loadJsonFile($file);
    if (!is_array($rows)) {
      return 0;
    }

    $count = 0;
    foreach ($rows as $row) {
      $table_id = $row['table_id'] ?? NULL;
      if (!$table_id) {
        continue;
      }

      $exists = $this->database->select('dungeoncrawler_content_loot_tables', 't')
        ->condition('table_id', $table_id)
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($exists && !$force) {
        continue;
      }

      $fields = [
        'table_id' => $table_id,
        'name' => $row['name'] ?? '',
        'description' => $row['description'] ?? '',
        'level_range' => $row['level_range'] ?? '',
        'entries' => is_array($row['entries'] ?? NULL) ? json_encode($row['entries']) : ($row['entries'] ?? '[]'),
        'created' => (int) ($row['created'] ?? time()),
      ];

      try {
        if ($exists && $force) {
          $this->database->delete('dungeoncrawler_content_loot_tables')
            ->condition('table_id', $table_id)
            ->execute();
        }
        $this->database->insert('dungeoncrawler_content_loot_tables')
          ->fields($fields)
          ->execute();
        $count++;
      }
      catch (\Exception $e) {
        $this->logger->warning('Failed to seed loot table @tid: @err', [
          '@tid' => $table_id,
          '@err' => $e->getMessage(),
        ]);
      }
    }

    return $count;
  }

  /**
   * Seed encounter templates from content/encounter_templates.json.
   *
   * Schema: template_id, name, description, level, xp_budget, threat_level,
   *         creature_slots, environment_tags.
   */
  public function seedEncounterTemplates(bool $force = FALSE): int {
    $file = $this->getContentPath() . '/encounter_templates.json';
    if (!file_exists($file)) {
      return 0;
    }

    $rows = $this->loadJsonFile($file);
    if (!is_array($rows)) {
      return 0;
    }

    $count = 0;
    foreach ($rows as $row) {
      $template_id = $row['template_id'] ?? NULL;
      if (!$template_id) {
        continue;
      }

      $exists = $this->database->select('dungeoncrawler_content_encounter_templates', 't')
        ->condition('template_id', $template_id)
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($exists && !$force) {
        continue;
      }

      $fields = [
        'template_id' => $template_id,
        'name' => $row['name'] ?? '',
        'description' => $row['description'] ?? '',
        'level' => (int) ($row['level'] ?? 1),
        'xp_budget' => (int) ($row['xp_budget'] ?? 0),
        'threat_level' => $row['threat_level'] ?? 'moderate',
        'creature_slots' => is_array($row['creature_slots'] ?? NULL) ? json_encode($row['creature_slots']) : ($row['creature_slots'] ?? '[]'),
        'environment_tags' => is_array($row['environment_tags'] ?? NULL) ? json_encode($row['environment_tags']) : ($row['environment_tags'] ?? ''),
      ];

      try {
        if ($exists && $force) {
          $this->database->delete('dungeoncrawler_content_encounter_templates')
            ->condition('template_id', $template_id)
            ->execute();
        }
        $this->database->insert('dungeoncrawler_content_encounter_templates')
          ->fields($fields)
          ->execute();
        $count++;
      }
      catch (\Exception $e) {
        $this->logger->warning('Failed to seed encounter template @tid: @err', [
          '@tid' => $template_id,
          '@err' => $e->getMessage(),
        ]);
      }
    }

    return $count;
  }

  /**
   * Seed quest templates from content/quest_templates.json.
   *
   * Schema: template_id, name, description, quest_type, level_min, level_max,
   *         tags, objectives_schema, rewards_schema, prerequisites,
   *         story_impact, estimated_duration_minutes, created, updated, version.
   */
  public function seedQuestTemplates(bool $force = FALSE): int {
    $file = $this->getContentPath() . '/quest_templates.json';
    if (!file_exists($file)) {
      return 0;
    }

    $rows = $this->loadJsonFile($file);
    if (!is_array($rows)) {
      return 0;
    }

    $count = 0;
    foreach ($rows as $row) {
      $template_id = $row['template_id'] ?? NULL;
      if (!$template_id) {
        continue;
      }

      $exists = $this->database->select('dungeoncrawler_content_quest_templates', 't')
        ->condition('template_id', $template_id)
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($exists && !$force) {
        continue;
      }

      $now = time();
      $fields = [
        'template_id' => $template_id,
        'name' => $row['name'] ?? '',
        'description' => $row['description'] ?? '',
        'quest_type' => $row['quest_type'] ?? 'main',
        'level_min' => (int) ($row['level_min'] ?? 1),
        'level_max' => (int) ($row['level_max'] ?? 20),
        'tags' => is_array($row['tags'] ?? NULL) ? json_encode($row['tags']) : ($row['tags'] ?? ''),
        'objectives_schema' => is_array($row['objectives_schema'] ?? NULL) ? json_encode($row['objectives_schema']) : ($row['objectives_schema'] ?? ''),
        'rewards_schema' => is_array($row['rewards_schema'] ?? NULL) ? json_encode($row['rewards_schema']) : ($row['rewards_schema'] ?? ''),
        'prerequisites' => is_array($row['prerequisites'] ?? NULL) ? json_encode($row['prerequisites']) : ($row['prerequisites'] ?? ''),
        'story_impact' => is_array($row['story_impact'] ?? NULL) ? json_encode($row['story_impact']) : ($row['story_impact'] ?? ''),
        'estimated_duration_minutes' => (int) ($row['estimated_duration_minutes'] ?? 60),
        'created' => (int) ($row['created'] ?? $now),
        'updated' => (int) ($row['updated'] ?? $now),
        'version' => $row['version'] ?? '1.0',
      ];

      try {
        if ($exists && $force) {
          $this->database->delete('dungeoncrawler_content_quest_templates')
            ->condition('template_id', $template_id)
            ->execute();
        }
        $this->database->insert('dungeoncrawler_content_quest_templates')
          ->fields($fields)
          ->execute();
        $count++;
      }
      catch (\Exception $e) {
        $this->logger->warning('Failed to seed quest template @tid: @err', [
          '@tid' => $template_id,
          '@err' => $e->getMessage(),
        ]);
      }
    }

    return $count;
  }

  /**
   * Seed image metadata from content/images/image_manifest.json.
   *
   * This seeds the dc_generated_images and dc_generated_image_links tables
   * from the manifest. The actual image files should already exist at their
   * file_uri paths (included in the repository).
   */
  public function seedImageManifest(bool $force = FALSE): int {
    $file = $this->getContentPath() . '/images/image_manifest.json';
    if (!file_exists($file)) {
      return 0;
    }

    $manifest = $this->loadJsonFile($file);
    if (!is_array($manifest)) {
      return 0;
    }

    $count = 0;
    foreach ($manifest as $entry) {
      $image = $entry['image'] ?? [];
      $links = $entry['links'] ?? [];

      $uuid = $image['image_uuid'] ?? NULL;
      if (!$uuid) {
        continue;
      }

      // Check if exists.
      $existing_id = $this->database->select('dc_generated_images', 'i')
        ->fields('i', ['id'])
        ->condition('image_uuid', $uuid)
        ->execute()
        ->fetchField();

      if ($existing_id && !$force) {
        continue;
      }

      $fields = [
        'image_uuid' => $uuid,
        'owner_uid' => (int) ($image['owner_uid'] ?? 1),
        'provider' => $image['provider'] ?? 'unknown',
        'provider_request_id' => $image['provider_request_id'] ?? '',
        'provider_model' => $image['provider_model'] ?? '',
        'status' => $image['status'] ?? 'ready',
        'mime_type' => $image['mime_type'] ?? 'image/png',
        'width' => (int) ($image['width'] ?? 0),
        'height' => (int) ($image['height'] ?? 0),
        'bytes' => (int) ($image['bytes'] ?? 0),
        'storage_scheme' => $image['storage_scheme'] ?? 'public',
        'file_uri' => $image['file_uri'] ?? '',
        'public_url' => $image['public_url'] ?? NULL,
        'sha256' => $image['sha256'] ?? '',
        'prompt_text' => $image['prompt_text'] ?? '',
        'negative_prompt' => $image['negative_prompt'] ?? '',
        'generation_params' => is_array($image['generation_params'] ?? NULL) ? json_encode($image['generation_params']) : ($image['generation_params'] ?? '{}'),
        'safety_metadata' => $image['safety_metadata'] ?? NULL,
        'created' => (int) ($image['created'] ?? time()),
        'updated' => (int) ($image['updated'] ?? time()),
        'deleted' => 0,
      ];

      try {
        if ($existing_id && $force) {
          $this->database->delete('dc_generated_image_links')
            ->condition('image_id', $existing_id)
            ->execute();
          $this->database->delete('dc_generated_images')
            ->condition('id', $existing_id)
            ->execute();
        }

        $image_id = $this->database->insert('dc_generated_images')
          ->fields($fields)
          ->execute();

        // Seed links.
        foreach ($links as $link) {
          $this->database->insert('dc_generated_image_links')
            ->fields([
              'image_id' => $image_id,
              'scope_type' => $link['scope_type'] ?? 'global',
              'campaign_id' => !empty($link['campaign_id']) ? (int) $link['campaign_id'] : NULL,
              'table_name' => $link['table_name'] ?? '',
              'object_id' => $link['object_id'] ?? '',
              'slot' => $link['slot'] ?? 'sprite',
              'variant' => $link['variant'] ?? 'original',
              'is_primary' => (int) ($link['is_primary'] ?? 1),
              'sort_weight' => (int) ($link['sort_weight'] ?? 0),
              'visibility' => $link['visibility'] ?? 'public',
              'created' => (int) ($link['created'] ?? time()),
              'updated' => (int) ($link['updated'] ?? time()),
            ])
            ->execute();
        }

        $count++;
      }
      catch (\Exception $e) {
        $this->logger->warning('Failed to seed image @uuid: @err', [
          '@uuid' => $uuid,
          '@err' => $e->getMessage(),
        ]);
      }
    }

    return $count;
  }

  /**
   * Copy packaged image files into the Drupal public files directory.
   *
   * Reads the image manifest and for each entry that has a seed_filename,
   * copies the file from content/images/files/ into the correct public://
   * path (recreating the year/month directory structure).
   *
   * @return int
   *   Number of image files copied.
   */
  public function copyImageFiles(): int {
    $manifest_path = $this->getContentPath() . '/images/image_manifest.json';
    if (!file_exists($manifest_path)) {
      return 0;
    }

    $manifest = $this->loadJsonFile($manifest_path);
    if (!is_array($manifest)) {
      return 0;
    }

    $files_dir = $this->getContentPath() . '/images/files';
    $count = 0;

    foreach ($manifest as $entry) {
      $image = $entry['image'] ?? [];
      $seed_filename = $entry['seed_filename'] ?? NULL;
      $file_uri = $image['file_uri'] ?? '';

      if (!$seed_filename || !$file_uri) {
        continue;
      }

      $src = $files_dir . '/' . $seed_filename;
      if (!file_exists($src)) {
        continue;
      }

      // Convert public://path to real filesystem path to check existence.
      // If the file already exists at the destination, skip.
      $dest_uri = $file_uri;
      if (strpos($dest_uri, 'temporary://') === 0) {
        // Remap temporary:// to public:// for permanence.
        $dest_uri = 'public://' . substr($dest_uri, strlen('temporary://'));
      }

      $real_dest = $this->fileSystem->realpath($dest_uri);
      if ($real_dest && file_exists($real_dest)) {
        continue;
      }

      // Ensure the destination directory exists.
      $dest_dir = dirname($dest_uri);
      try {
        $this->fileSystem->prepareDirectory($dest_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $this->fileSystem->copy($src, $dest_uri, FileSystemInterface::EXISTS_REPLACE);
        $count++;
      }
      catch (\Exception $e) {
        $this->logger->warning('Failed to copy image file @name to @dest: @err', [
          '@name' => $seed_filename,
          '@dest' => $dest_uri,
          '@err' => $e->getMessage(),
        ]);
      }
    }

    if ($count > 0) {
      $this->logger->info('Copied @count image files to public files directory.', [
        '@count' => $count,
      ]);
    }

    return $count;
  }

  /**
   * Seed image prompt cache from content/images/prompt_cache.json.
   *
   * Schema: provider, provider_model, prompt_hash, prompt_text,
   *         negative_prompt, style, aspect_ratio, status,
   *         request_payload, response_payload, output_payload,
   *         campaign_id, map_id, dungeon_id, room_id, hex_q, hex_r,
   *         entity_type, terrain_type, habitat_name, created, updated, hits.
   */
  public function seedPromptCache(bool $force = FALSE): int {
    $file = $this->getContentPath() . '/images/prompt_cache_manifest.json';
    if (!file_exists($file)) {
      $file = $this->getContentPath() . '/images/prompt_cache.json';
    }
    if (!file_exists($file)) {
      return 0;
    }

    $rows = $this->loadJsonFile($file);
    if (!is_array($rows)) {
      return 0;
    }

    $count = 0;
    foreach ($rows as $row) {
      $prompt_hash = $row['prompt_hash'] ?? NULL;
      if (!$prompt_hash) {
        continue;
      }

      $exists = $this->database->select('dungeoncrawler_content_image_prompt_cache', 'c')
        ->condition('prompt_hash', $prompt_hash)
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($exists && !$force) {
        continue;
      }

      $fields = [
        'provider' => $row['provider'] ?? '',
        'provider_model' => $row['provider_model'] ?? '',
        'prompt_hash' => $prompt_hash,
        'prompt_text' => $row['prompt_text'] ?? '',
        'negative_prompt' => $row['negative_prompt'] ?? '',
        'style' => $row['style'] ?? '',
        'aspect_ratio' => $row['aspect_ratio'] ?? '',
        'status' => $row['status'] ?? 'ready',
        'request_payload' => $this->encodePromptCachePayload($row['request_payload'] ?? ''),
        'response_payload' => $this->encodePromptCachePayload($row['response_payload'] ?? ''),
        'output_payload' => $this->encodePromptCachePayload($row['output_payload'] ?? ''),
        'campaign_id' => !empty($row['campaign_id']) ? (int) $row['campaign_id'] : NULL,
        'map_id' => $row['map_id'] ?? NULL,
        'dungeon_id' => $row['dungeon_id'] ?? NULL,
        'room_id' => $row['room_id'] ?? NULL,
        'hex_q' => isset($row['hex_q']) ? (int) $row['hex_q'] : NULL,
        'hex_r' => isset($row['hex_r']) ? (int) $row['hex_r'] : NULL,
        'entity_type' => $row['entity_type'] ?? '',
        'terrain_type' => $row['terrain_type'] ?? '',
        'habitat_name' => $row['habitat_name'] ?? '',
        'created' => (int) ($row['created'] ?? time()),
        'updated' => (int) ($row['updated'] ?? time()),
        'hits' => (int) ($row['hits'] ?? 0),
      ];

      try {
        if ($exists && $force) {
          $this->database->delete('dungeoncrawler_content_image_prompt_cache')
            ->condition('prompt_hash', $prompt_hash)
            ->execute();
        }
        $this->database->insert('dungeoncrawler_content_image_prompt_cache')
          ->fields($fields)
          ->execute();
        $count++;
      }
      catch (\Exception $e) {
        $this->logger->warning('Failed to seed prompt cache @key: @err', [
          '@key' => $prompt_hash,
          '@err' => $e->getMessage(),
        ]);
      }
    }

    return $count;
  }

  /**
   * Load and decode a JSON file.
   *
   * @param string $path
   *   Absolute file path.
   *
   * @return array|null
   *   Decoded JSON data or NULL on failure.
   */
  protected function loadJsonFile(string $path): ?array {
    if (!file_exists($path) || !is_readable($path)) {
      $this->logger->warning('Seed file not found or not readable: @path', [
        '@path' => $path,
      ]);
      return NULL;
    }

    $content = file_get_contents($path);
    $data = json_decode($content, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->logger->warning('Invalid JSON in seed file @path: @err', [
        '@path' => $path,
        '@err' => json_last_error_msg(),
      ]);
      return NULL;
    }

    return $data;
  }

  /**
   * Re-export all current DB content to JSON seed files.
   *
   * This is a development utility to sync DB state back into
   * the packaged content files after manual edits or AI generation.
   *
   * @return array
   *   Summary of exported counts per category.
   */
  public function exportAll(): array {
    $summary = [];
    $base = $this->getContentPath();

    $summary['setting_templates'] = $this->exportTable(
      'dungeoncrawler_content_setting_templates',
      $base . '/setting_templates',
      'template_id',
      ['setting_data', 'search_tags']
    );

    $summary['room_templates'] = $this->exportTable(
      'dungeoncrawler_content_room_templates',
      $base . '/room_templates',
      'template_id',
      ['layout_data', 'contents_data', 'environment_tags', 'search_tags']
    );

    $summary['loot_tables'] = $this->exportBulkTable(
      'dungeoncrawler_content_loot_tables',
      $base . '/loot_tables.json',
      ['entries']
    );

    $summary['encounter_templates'] = $this->exportBulkTable(
      'dungeoncrawler_content_encounter_templates',
      $base . '/encounter_templates.json',
      ['creature_slots']
    );

    $summary['quest_templates'] = $this->exportBulkTable(
      'dungeoncrawler_content_quest_templates',
      $base . '/quest_templates.json',
      ['tags', 'objectives_schema', 'rewards_schema', 'prerequisites', 'story_impact']
    );

    $summary['prompt_cache'] = $this->exportPromptCacheManifest();

    $summary['images'] = $this->exportImageManifest();

    return $summary;
  }

  /**
   * Export a table to individual JSON files.
   *
   * @param string $table
   *   Table name.
   * @param string $dir
   *   Target directory.
   * @param string $id_field
   *   Field to use for filename.
   * @param array $json_fields
   *   Fields that contain JSON strings to decode.
   *
   * @return int
   *   Number of files exported.
   */
  protected function exportTable(string $table, string $dir, string $id_field, array $json_fields = []): int {
    if (!is_dir($dir)) {
      @mkdir($dir, 0755, TRUE);
    }

    $rows = $this->database->select($table, 't')
      ->fields('t')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($rows as $row) {
      // Decode JSON fields.
      foreach ($json_fields as $field) {
        if (isset($row[$field]) && is_string($row[$field])) {
          $row[$field] = json_decode($row[$field], TRUE) ?: [];
        }
      }

      // Remove auto-increment and timestamps.
      unset($row['id']);
      unset($row['created']);
      unset($row['updated']);

      $filename = $row[$id_field] . '.json';
      $path = $dir . '/' . $filename;
      file_put_contents($path, json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
      $count++;
    }

    return $count;
  }

  /**
   * Export a table to a single JSON array file.
   *
   * @param string $table
   *   Table name.
   * @param string $file
   *   Target file path.
   * @param array $json_fields
   *   Fields that contain JSON strings to decode.
   *
   * @return int
   *   Number of rows exported.
   */
  protected function exportBulkTable(string $table, string $file, array $json_fields = []): int {
    $dir = dirname($file);
    if (!is_dir($dir)) {
      @mkdir($dir, 0755, TRUE);
    }

    $rows = $this->database->select($table, 't')
      ->fields('t')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
      unset($row['id']);
      foreach ($json_fields as $field) {
        if (isset($row[$field]) && is_string($row[$field])) {
          $decoded = json_decode($row[$field], TRUE);
          if ($decoded !== NULL) {
            $row[$field] = $decoded;
          }
        }
      }
    }

    file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    return count($rows);
  }

  /**
   * Export image manifest from dc_generated_images + dc_generated_image_links.
   *
   * @return int
   *   Number of image records exported.
   */
  protected function exportImageManifest(): int {
    $base = $this->getContentPath();
    $files_dir = $base . '/images/files';
    $manifest_path = $base . '/images/image_manifest.json';

    if (!is_dir(dirname($manifest_path))) {
      @mkdir(dirname($manifest_path), 0755, TRUE);
    }

    $images = $this->database->select('dc_generated_images', 'i')
      ->fields('i')
      ->condition('deleted', 0)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $manifest = [];
    foreach ($images as $image) {
      $image_id = $image['id'];
      unset($image['id']);

      // Decode JSON fields.
      if (isset($image['generation_params']) && is_string($image['generation_params'])) {
        $decoded = json_decode($image['generation_params'], TRUE);
        if ($decoded !== NULL) {
          $image['generation_params'] = $decoded;
        }
      }

      // Get linked records.
      $links = $this->database->select('dc_generated_image_links', 'l')
        ->fields('l')
        ->condition('image_id', $image_id)
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);

      foreach ($links as &$link) {
        unset($link['id']);
        unset($link['image_id']);
      }

      $entry = ['image' => $image, 'links' => $links];

      // Check seed_filename availability.
      $filename = basename($image['file_uri'] ?? '');
      if ($filename && is_dir($files_dir) && file_exists($files_dir . '/' . $filename)) {
        $entry['seed_filename'] = $filename;
      }

      $manifest[] = $entry;
    }

    file_put_contents($manifest_path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    return count($manifest);
  }

  /**
   * Export prompt cache metadata to a slim manifest and archive unique images.
   *
   * This avoids storing inline base64 image payloads in git while preserving
   * prompt metadata and keeping image bytes available either through the main
   * generated image manifest or a prompt-cache-specific archive file.
   *
   * @return int
   *   Number of prompt cache rows exported.
   */
  protected function exportPromptCacheManifest(): int {
    $base = $this->getContentPath();
    $manifest_path = $base . '/images/prompt_cache_manifest.json';
    $archive_dir = $base . '/images/prompt-cache-files';

    if (!is_dir(dirname($manifest_path))) {
      @mkdir(dirname($manifest_path), 0755, TRUE);
    }
    if (!is_dir($archive_dir)) {
      @mkdir($archive_dir, 0755, TRUE);
    }

    $rows = $this->database->select('dungeoncrawler_content_image_prompt_cache', 't')
      ->fields('t')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $generated_images = $this->database->select('dc_generated_images', 'i')
      ->fields('i', ['image_uuid', 'file_uri', 'public_url', 'sha256', 'mime_type', 'bytes', 'width', 'height'])
      ->condition('deleted', 0)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $generated_by_hash = [];
    foreach ($generated_images as $generated_image) {
      if (!empty($generated_image['sha256'])) {
        $generated_by_hash[$generated_image['sha256']] = $generated_image;
      }
    }

    foreach ($rows as &$row) {
      unset($row['id']);

      $prompt_hash = $row['prompt_hash'] ?? sha1((string) json_encode($row));
      $request_payload = $this->decodePromptCachePayload($row['request_payload'] ?? '');
      $response_payload = $this->decodePromptCachePayload($row['response_payload'] ?? '');
      $output_payload = $this->decodePromptCachePayload($row['output_payload'] ?? '');

      $artifact = $this->extractPromptCacheImageArtifact($output_payload, $prompt_hash, $archive_dir);
      if (!$artifact) {
        $artifact = $this->extractPromptCacheImageArtifact($response_payload, $prompt_hash, $archive_dir);
      }

      if ($artifact && !empty($generated_by_hash[$artifact['sha256']])) {
        $generated_image = $generated_by_hash[$artifact['sha256']];
        $artifact = [
          'source' => 'generated_image_manifest',
          'image_uuid' => $generated_image['image_uuid'] ?? NULL,
          'file_uri' => $generated_image['file_uri'] ?? NULL,
          'public_url' => $generated_image['public_url'] ?? NULL,
          'sha256' => $generated_image['sha256'] ?? NULL,
          'mime_type' => $generated_image['mime_type'] ?? NULL,
          'bytes' => isset($generated_image['bytes']) ? (int) $generated_image['bytes'] : NULL,
          'width' => isset($generated_image['width']) ? (int) $generated_image['width'] : NULL,
          'height' => isset($generated_image['height']) ? (int) $generated_image['height'] : NULL,
        ];
      }

      if (is_array($request_payload)) {
        $row['request_payload'] = $this->stripInlineImageDataFromPayload($request_payload, $artifact);
      }
      if (is_array($response_payload)) {
        $row['response_payload'] = $this->stripInlineImageDataFromPayload($response_payload, $artifact);
      }
      if (is_array($output_payload)) {
        $row['output_payload'] = $this->stripInlineImageDataFromPayload($output_payload, $artifact);
      }

      if ($artifact) {
        $row['cached_image'] = $artifact;
      }
    }

    file_put_contents($manifest_path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    return count($rows);
  }

  /**
   * Decode a prompt cache payload when it is JSON-encoded.
   *
   * @param mixed $payload
   *   Raw payload.
   *
   * @return mixed
   *   Decoded array if JSON, otherwise the original scalar value.
   */
  protected function decodePromptCachePayload(mixed $payload): mixed {
    if (!is_string($payload) || $payload === '') {
      return $payload;
    }

    $decoded = json_decode($payload, TRUE);
    return $decoded !== NULL ? $decoded : $payload;
  }

  /**
   * Encode a prompt cache payload for database storage.
   *
   * @param mixed $payload
   *   Payload value.
   *
   * @return string
   *   Encoded payload string.
   */
  protected function encodePromptCachePayload(mixed $payload): string {
    if (is_array($payload)) {
      return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
    return (string) $payload;
  }

  /**
   * Extract and persist an image artifact referenced by a data URI payload.
   *
   * @param mixed $payload
   *   Decoded payload array.
   * @param string $prompt_hash
   *   Prompt cache key.
   * @param string $archive_dir
   *   Destination directory for archived prompt cache images.
   *
   * @return array|null
   *   Archived image metadata or NULL if no embedded image was found.
   */
  protected function extractPromptCacheImageArtifact(mixed $payload, string $prompt_hash, string $archive_dir): ?array {
    $data_uri = $this->findDataUriInPayload($payload);
    if (!$data_uri) {
      return NULL;
    }

    if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', $data_uri, $matches)) {
      return NULL;
    }

    $mime_type = $matches[1];
    $binary = base64_decode($matches[2], TRUE);
    if ($binary === FALSE) {
      return NULL;
    }

    $extension = match ($mime_type) {
      'image/jpeg' => 'jpg',
      'image/webp' => 'webp',
      'image/gif' => 'gif',
      default => 'png',
    };

    $filename = $prompt_hash . '.' . $extension;
    $path = $archive_dir . '/' . $filename;
    if (!file_exists($path)) {
      file_put_contents($path, $binary);
    }

    return [
      'source' => 'prompt_cache_archive',
      'seed_filename' => $filename,
      'relative_path' => 'images/prompt-cache-files/' . $filename,
      'mime_type' => $mime_type,
      'bytes' => strlen($binary),
      'sha256' => hash('sha256', $binary),
    ];
  }

  /**
   * Find a data URI anywhere inside a nested payload structure.
   *
   * @param mixed $payload
   *   Payload to inspect.
   *
   * @return string|null
   *   Data URI string if found.
   */
  protected function findDataUriInPayload(mixed $payload): ?string {
    if (is_string($payload) && str_starts_with($payload, 'data:image/')) {
      return $payload;
    }

    if (!is_array($payload)) {
      return NULL;
    }

    foreach ($payload as $value) {
      $found = $this->findDataUriInPayload($value);
      if ($found) {
        return $found;
      }
    }

    return NULL;
  }

  /**
   * Remove inline image data from a payload and replace it with file refs.
   *
   * @param mixed $payload
   *   Payload to sanitize.
   * @param array|null $artifact
   *   Archived or linked image metadata.
   *
   * @return mixed
   *   Sanitized payload.
   */
  protected function stripInlineImageDataFromPayload(mixed $payload, ?array $artifact = NULL): mixed {
    if (is_string($payload) && str_starts_with($payload, 'data:image/')) {
      return $artifact ? '[image archived externally]' : '[image data removed]';
    }

    if (!is_array($payload)) {
      return $payload;
    }

    foreach ($payload as $key => $value) {
      if (is_string($value) && str_starts_with($value, 'data:image/')) {
        unset($payload[$key]);
        continue;
      }

      $payload[$key] = $this->stripInlineImageDataFromPayload($value, $artifact);
    }

    if ($artifact) {
      $payload['image_ref'] = $artifact;
    }

    return $payload;
  }

}
