<?php

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;

/**
 * Stages attested original scene backgrounds and tiles at source resolution.
 *
 * Does not stage portraits or tokens. The release integration is
 * responsible for supplying a trusted inspector inventory and rights record.
 */
class ModuleSceneMapStagingService {

  private const MAX_ARCHIVE_BYTES = 536870912;
  private const MAX_IMAGE_BYTES = 33554432;
  private const MAX_DIMENSION = 8192;
  private const MAX_PIXELS = 33554432;
  private const ISLAND_LICENSE_SHA256 = '78051a4c5e7dfc9a48e7d7d5a793a7b51bbf7a567e3fe79cf8c1884810e582d9';

  public function __construct(
    private readonly GeneratedImageRepository $images,
  ) {}

  /**
   * Stage one inspected scene background; never resample the source binary.
   *
   * Rights must contain attested=true, asset_kind=original_scene_map,
   * archive_sha256, entry, scene_resource_id, source_url (HTTPS), author,
   * license_id=MIT, license_url and the complete upstream license_notice. The notice
   * is persisted in generation_params with the source and archive hashes.
   *
   * @param array<string, mixed> $inventory
   *   Unmodified result of ModuleConversionArchiveInspectorService::inspectArchive().
   * @param array<string, mixed> $rights
   *   Explicit, per-map rights attestation.
   *
   * @return array<string, mixed>
   *   Repository persistence summary plus scene and source provenance.
   */
  public function stageSceneBackground(string $archive_path, array $inventory, string $scene_resource_id, array $rights, int $owner_uid): array {
    return $this->stageSceneImage($archive_path, $inventory, $scene_resource_id, $rights, $owner_uid, NULL);
  }

  /**
   * Stage a scene map tile by its original placement index.
   */
  public function stageSceneTile(string $archive_path, array $inventory, string $scene_resource_id, int $tile_index, array $rights, int $owner_uid): array {
    if ($tile_index < 0) {
      throw new \InvalidArgumentException('source_map_tile_index_invalid');
    }
    return $this->stageSceneImage($archive_path, $inventory, $scene_resource_id, $rights, $owner_uid, $tile_index);
  }

  /**
   * Stage every image reference for one scene as a single reviewed batch.
   *
   * Returns both public presentation descriptors and repository receipts for
   * compensation if the enclosing release transaction later fails.
   */
  public function stageSceneAssets(string $archive_path, array $inventory, string $scene_resource_id, array $attestations, int $owner_uid): array {
    $scene = NULL;
    foreach ((array) ($inventory['resources'] ?? []) as $resource) {
      if (($resource['resource_id'] ?? NULL) === $scene_resource_id && ($resource['resource_type'] ?? NULL) === 'scene') {
        $scene = $resource;
        break;
      }
    }
    $context = $scene['source_context'] ?? NULL;
    if (!is_array($context) || !is_array($attestations['tiles'] ?? NULL)
      || !array_is_list($attestations['tiles']) || !is_array($attestations['background'] ?? NULL)
      || !is_array($context['tile_image_refs'] ?? NULL)
      || count($attestations['tiles']) !== count($context['tile_image_refs'])) {
      throw new \InvalidArgumentException('source_map_attestation_coverage_invalid');
    }
    $stored = [];
    try {
      $background = $this->stageSceneBackground($archive_path, $inventory, $scene_resource_id, $attestations['background'], $owner_uid);
      $stored[] = $background;
      $tiles = [];
      foreach ($attestations['tiles'] as $index => $rights) {
        if (!is_array($rights)) {
          throw new \InvalidArgumentException('source_map_attestation_coverage_invalid');
        }
        $tile = $this->stageSceneTile($archive_path, $inventory, $scene_resource_id, $index, $rights, $owner_uid);
        $stored[] = $tile;
        $tiles[] = $this->mapDescriptor($tile) + ['placement' => $context['tile_placements'][$index] ?? NULL];
      }
      return [
        'maps' => ['background' => $this->mapDescriptor($background), 'tiles' => $tiles],
        'stored' => $stored,
      ];
    }
    catch (\Throwable $exception) {
      $this->discardSceneAssets($stored);
      throw $exception;
    }
  }

  public function discardSceneAssets(array $stored): void {
    foreach (array_reverse($stored) as $image) {
      $this->images->discardSourceMap($image);
    }
  }

  private function mapDescriptor(array $stored): array {
    $provenance = $stored['provenance'];
    return [
      'image_id' => $stored['image_id'],
      'url' => $stored['url'],
      'license_notice_url' => $stored['license_notice_url'],
      'width' => $stored['width'],
      'height' => $stored['height'],
      'sha256' => $stored['sha256'],
      'archive_entry' => $provenance['archive_entry'],
      'author' => $provenance['author'],
      'source_url' => $provenance['source_url'],
      'license_url' => $provenance['license_url'],
    ];
  }

  private function stageSceneImage(string $archive_path, array $inventory, string $scene_resource_id, array $rights, int $owner_uid, ?int $tile_index): array {
    $archive = $inventory['archive'] ?? NULL;
    $manifest = $inventory['manifest'] ?? NULL;
    if (($inventory['schema_version'] ?? NULL) !== 'module-conversion-inventory-v1' || !is_array($archive) || !is_array($manifest)) {
      throw new \InvalidArgumentException('source_map_inventory_invalid');
    }
    $expected_hash = $archive['sha256'] ?? NULL;
    $resolved = realpath($archive_path);
    if (!is_string($resolved) || !is_file($resolved) || !is_string($archive['path'] ?? NULL)
      || $resolved !== $archive['path'] || !is_string($expected_hash) || !preg_match('/^[a-f0-9]{64}$/D', $expected_hash)
      || !is_int($archive['bytes'] ?? NULL) || $archive['bytes'] < 1 || $archive['bytes'] > self::MAX_ARCHIVE_BYTES
      || filesize($resolved) !== $archive['bytes'] || !hash_equals($expected_hash, hash_file('sha256', $resolved) ?: '')) {
      throw new \InvalidArgumentException('source_map_archive_mismatch');
    }
    $module_id = $manifest['id'] ?? NULL;
    if (!is_string($module_id) || !preg_match('/^[a-zA-Z0-9_-]+$/D', $module_id)) {
      throw new \InvalidArgumentException('source_map_inventory_invalid');
    }
    $scene = NULL;
    if (!is_array($inventory['resources'] ?? NULL)) {
      throw new \InvalidArgumentException('source_map_inventory_invalid');
    }
    foreach ($inventory['resources'] as $resource) {
      if (is_array($resource) && ($resource['resource_id'] ?? NULL) === $scene_resource_id && ($resource['resource_type'] ?? NULL) === 'scene') {
        if ($scene !== NULL) {
          throw new \InvalidArgumentException('source_map_scene_ambiguous');
        }
        $scene = $resource;
      }
    }
    $context = $scene['source_context'] ?? NULL;
    $entry = is_array($context)
      ? ($tile_index === NULL ? ($context['background_archive_entry'] ?? NULL) : ($context['tile_archive_entries'][$tile_index] ?? NULL))
      : NULL;
    $reference = is_array($context)
      ? ($tile_index === NULL ? ($context['background_src'] ?? NULL) : ($context['tile_image_refs'][$tile_index] ?? NULL))
      : NULL;
    $root = $archive['module_root'] ?? NULL;
    if (!is_string($entry) || !is_string($reference) || !is_string($root)
      || $scene_resource_id === '' || !str_starts_with($reference, 'modules/' . $module_id . '/')) {
      throw new \InvalidArgumentException('source_map_scene_image_missing');
    }
    $suffix = rawurldecode(substr($reference, strlen('modules/' . $module_id . '/')));
    $expected_entry = ($root !== '' ? $root . '/' : '') . $suffix;
    if (!$this->safePath($entry) || ($root !== '' && !$this->safePath($root)) || $entry !== $expected_entry
      || preg_match('~(?:^|/)(?:portrait|portraits|token|tokens)(?:/|$)~i', $entry)) {
      throw new \InvalidArgumentException('source_map_unsafe_entry');
    }
    if ($module_id === 'the-island-adventure' && ($manifest['version'] ?? NULL) !== '1.1.0') {
      throw new \InvalidArgumentException('source_map_island_version_unattested');
    }
    $this->assertRights($rights, $expected_hash, $entry, $scene_resource_id, $module_id);
    if ($owner_uid < 0) {
      throw new \InvalidArgumentException('source_map_owner_invalid');
    }

    $zip = new \ZipArchive();
    if ($zip->open($resolved, \ZipArchive::RDONLY) !== TRUE) {
      throw new \RuntimeException('source_map_archive_open_failed');
    }
    try {
      $index = $zip->locateName($entry, 0);
      $stat = $index !== FALSE ? $zip->statIndex($index) : FALSE;
      if ($index !== FALSE) {
        for ($other = $index + 1; $other < $zip->count(); $other++) {
          if ($zip->getNameIndex($other) === $entry) {
            throw new \InvalidArgumentException('source_map_entry_ambiguous');
          }
        }
      }
      if (!is_array($stat) || ($stat['name'] ?? NULL) !== $entry || !is_int($stat['size'] ?? NULL)
        || $stat['size'] < 1 || $stat['size'] > self::MAX_IMAGE_BYTES
        || !is_int($stat['comp_size'] ?? NULL) || $stat['comp_size'] < 1
        || $stat['size'] > $stat['comp_size'] * 300) {
        throw new \InvalidArgumentException('source_map_entry_missing_or_oversized');
      }
      if ($zip->getExternalAttributesIndex($index, $opsys, $attributes)
        && $opsys === \ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
        throw new \InvalidArgumentException('source_map_unsafe_entry');
      }
      $stream = $zip->getStream($entry);
      if (!is_resource($stream)) {
        throw new \RuntimeException('source_map_entry_read_failed');
      }
      try {
        $binary = '';
        while (!feof($stream)) {
          $part = fread($stream, min(65536, self::MAX_IMAGE_BYTES + 1 - strlen($binary)));
          if ($part === FALSE || ($part === '' && !feof($stream))) {
            throw new \RuntimeException('source_map_entry_read_failed');
          }
          $binary .= $part;
          if (strlen($binary) > self::MAX_IMAGE_BYTES) {
            throw new \InvalidArgumentException('source_map_image_oversized');
          }
        }
      }
      finally {
        fclose($stream);
      }
      if (strlen($binary) !== $stat['size']) {
        throw new \RuntimeException('source_map_entry_size_mismatch');
      }
    }
    finally {
      $zip->close();
    }
    if (filesize($resolved) !== $archive['bytes'] || !hash_equals($expected_hash, hash_file('sha256', $resolved) ?: '')) {
      throw new \InvalidArgumentException('source_map_archive_mismatch');
    }
    $metadata = getimagesizefromstring($binary);
    $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];
    $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
    $mime = is_array($metadata) ? ($metadata['mime'] ?? NULL) : NULL;
    $width = is_array($metadata) ? ($metadata[0] ?? NULL) : NULL;
    $height = is_array($metadata) ? ($metadata[1] ?? NULL) : NULL;
    if (!isset($types[$extension]) || $mime !== $types[$extension]
      || !is_int($width) || !is_int($height) || $width < 1 || $height < 1
      || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION || $width * $height > self::MAX_PIXELS
      || !function_exists('imagecreatefromstring')) {
      throw new \InvalidArgumentException('source_map_image_unsupported');
    }
    $decoded = @imagecreatefromstring($binary);
    if ($decoded === FALSE) {
      throw new \InvalidArgumentException('source_map_image_unsupported');
    }
    imagedestroy($decoded);
    $provenance = [
      'kind' => 'original_scene_map',
      'map_role' => $tile_index === NULL ? 'background' : 'tile',
      'tile_index' => $tile_index,
      'module_id' => $module_id,
      'module_version' => (string) ($manifest['version'] ?? ''),
      'scene_resource_id' => $scene_resource_id,
      'archive_sha256' => $expected_hash,
      'archive_entry' => $entry,
      'source_reference' => $reference,
      'source_sha256' => hash('sha256', $binary),
      'source_url' => $rights['source_url'],
      'license_url' => $rights['license_url'],
      'author' => $rights['author'],
      'license_id' => 'MIT',
      'license_notice' => $rights['license_notice'],
      'rights_attested' => TRUE,
    ];
    try {
      $stored = $this->images->persistSourceMap($binary, $mime, $width, $height, $provenance, $owner_uid);
    }
    catch (\Throwable $exception) {
      throw new \RuntimeException('source_map_repository_failed', 0, $exception);
    }
    if (($stored['stored'] ?? NULL) !== TRUE || !is_int($stored['image_id'] ?? NULL) || $stored['image_id'] < 1
      || !is_string($stored['file_uri'] ?? NULL) || !str_starts_with($stored['file_uri'], 'public://generated-images/')
      || ($stored['sha256'] ?? NULL) !== $provenance['source_sha256']) {
      throw new \RuntimeException('source_map_repository_failed');
    }
    return $stored + ['provenance' => $provenance, 'width' => $width, 'height' => $height, 'mime_type' => $mime];
  }

  private function safePath(string $path): bool {
    if ($path === '' || str_contains($path, '\\') || str_contains($path, "\0") || str_starts_with($path, '/')) {
      return FALSE;
    }
    foreach (explode('/', $path) as $part) {
      if ($part === '' || $part === '.' || $part === '..' || preg_match('/[\x00-\x1f\x7f]/', $part)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * @param array<string, mixed> $rights
   */
  private function assertRights(array $rights, string $archive_hash, string $entry, string $scene_id, string $module_id): void {
    $notice = $rights['license_notice'] ?? NULL;
    $required = [
      'MIT License', 'Copyright (c)', 'Permission is hereby granted, free of charge',
      'The above copyright notice and this permission notice shall be included',
      'THE SOFTWARE IS PROVIDED "AS IS"', 'LIABILITY, WHETHER IN AN ACTION OF CONTRACT',
      'SOFTWARE.',
    ];
    if (($rights['attested'] ?? NULL) !== TRUE || ($rights['asset_kind'] ?? NULL) !== 'original_scene_map'
      || ($rights['license_id'] ?? NULL) !== 'MIT' || ($rights['archive_sha256'] ?? NULL) !== $archive_hash
      || ($rights['entry'] ?? NULL) !== $entry || ($rights['scene_resource_id'] ?? NULL) !== $scene_id
      || !is_string($rights['author'] ?? NULL) || trim($rights['author']) === ''
      || !is_string($rights['source_url'] ?? NULL) || !preg_match('~^https://[^/\s]+/\S+$~', $rights['source_url'])
      || !is_string($rights['license_url'] ?? NULL) || !preg_match('~^https://[^/\s]+/\S+$~', $rights['license_url'])
      || !is_string($notice) || strlen($notice) < 950) {
      throw new \InvalidArgumentException('source_map_rights_attestation_missing');
    }
    foreach ($required as $phrase) {
      if (!str_contains($notice, $phrase)) {
        throw new \InvalidArgumentException('source_map_rights_notice_incomplete');
      }
    }
    if ($module_id === 'the-island-adventure'
      && (!str_contains($notice, 'Copyright (c) 2024 mccaffertyt')
        || hash('sha256', trim(str_replace("\r\n", "\n", $notice))) !== self::ISLAND_LICENSE_SHA256
        || $rights['author'] !== 'Tom McCafferty'
        || !str_starts_with($rights['source_url'], 'https://github.com/mccaffertyt/the-island-adventure/')
        || ($rights['license_url'] ?? NULL) !== 'https://raw.githubusercontent.com/mccaffertyt/the-island-adventure/1.1.0/LICENSE')) {
      throw new \InvalidArgumentException('source_map_island_rights_invalid');
    }
  }

}
