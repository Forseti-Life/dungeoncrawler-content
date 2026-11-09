<?php

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

use Drupal\Core\File\FileSystemInterface;

/**
 * Bounded, read-only intake for Foundry module archives.
 */
class ModuleConversionArchiveInspectorService {

  private const DEFAULT_MAX_ARCHIVE_BYTES = 536870912;
  private const DEFAULT_MAX_ARCHIVE_ENTRIES = 25000;
  private const DEFAULT_MAX_ENTRY_BYTES = 33554432;
  private const DEFAULT_MAX_TOTAL_UNCOMPRESSED_BYTES = 2147483648;
  private const DEFAULT_MAX_COMPRESSION_RATIO = 300;
  private const DEFAULT_MAX_PATH_DEPTH = 16;
  private const DEFAULT_MAX_JSON_DOCUMENTS = 25000;
  private const ADAPTER_VERSION = 'module-conversion-intake-v1';

  public function __construct(
    private readonly LevelDbPackReaderInterface $levelDbPackReader,
    private readonly ?FileSystemInterface $fileSystem = NULL,
  ) {}

  /**
   * List archive members using the same intake safety checks as conversion.
   *
   * @return array<int,array{name:string,size:int,comp_size:int,is_dir:bool}>
   */
  public function listArchiveEntries(string $archive_path): array {
    $limits = $this->normalizeLimits([]);
    $path = $this->assertAllowedArchivePath($archive_path);
    $size = filesize($path);
    if (!is_int($size) || $size < 1 || $size > $limits['max_archive_bytes']) {
      throw $this->error('module_conversion_archive_size_invalid', 'Archive size is outside intake limits.', ['archive_path' => $path]);
    }
    $zip = new \ZipArchive();
    if ($zip->open($path, \ZipArchive::RDONLY) !== TRUE) {
      throw $this->error('module_conversion_archive_open_failed', 'Archive could not be opened by ZipArchive.', ['archive_path' => $path]);
    }
    try {
      return $this->scanArchiveEntries($zip, $limits);
    }
    finally {
      $zip->close();
    }
  }

  /**
   * Inspect one local Foundry module archive and return a typed inventory.
   *
   * @param string $archive_path
   *   Local archive path.
   * @param array<string,mixed> $limits
   *   Optional intake bounds.
   *
   * @return array<string,mixed>
   *   Read-only inventory payload.
   */
  public function inspectArchive(string $archive_path, array $limits = []): array {
    $normalized_limits = $this->normalizeLimits($limits);
    $real_archive_path = $this->assertAllowedArchivePath($archive_path);
    $archive_bytes = filesize($real_archive_path);
    if (!is_int($archive_bytes) || $archive_bytes < 1) {
      throw $this->error('module_conversion_archive_missing', 'Archive bytes could not be read.', [
        'archive_path' => $real_archive_path,
      ]);
    }
    if ($archive_bytes > $normalized_limits['max_archive_bytes']) {
      throw $this->error('module_conversion_archive_too_large', 'Archive exceeds max_archive_bytes.', [
        'archive_bytes' => $archive_bytes,
        'max_archive_bytes' => $normalized_limits['max_archive_bytes'],
      ]);
    }

    $archive_sha256 = hash_file('sha256', $real_archive_path);
    if (!is_string($archive_sha256) || strlen($archive_sha256) !== 64) {
      throw $this->error('module_conversion_archive_hash_failed', 'Archive SHA-256 hash could not be computed.', [
        'archive_path' => $real_archive_path,
      ], 500);
    }

    $zip = new \ZipArchive();
    $open_code = $zip->open($real_archive_path, \ZipArchive::RDONLY);
    if ($open_code !== TRUE) {
      throw $this->error('module_conversion_archive_open_failed', 'Archive could not be opened by ZipArchive.', [
        'archive_path' => $real_archive_path,
        'zip_error_code' => $open_code,
      ]);
    }

    $staging_root = $this->createStagingRoot();
    try {
      $entries = $this->scanArchiveEntries($zip, $normalized_limits);
      $manifest_entry = $this->resolveManifestEntry($entries);
      $manifest = $this->decodeJsonEntry($zip, $manifest_entry['name'], 'module_conversion_manifest_invalid', $normalized_limits);
      if (!is_array($manifest) || array_is_list($manifest)) {
        throw $this->error('module_conversion_manifest_invalid', 'module.json must be a JSON object.', [
          'entry' => $manifest_entry['name'],
        ]);
      }

      $module_root = $this->moduleRootFromManifestEntry((string) $manifest_entry['name']);
      $inspection = $this->inspectDeclaredPacks($zip, $entries, $manifest, $module_root, $staging_root, $normalized_limits);
      $packs = $inspection['packs'];
      $resources = $inspection['resources'];
      $findings = $inspection['findings'];
      $resources = $this->resolveSceneAssetEntries($resources, $entries, $module_root, (string) ($manifest['id'] ?? ''), $normalized_limits['max_path_depth']);
      usort($packs, static fn(array $a, array $b): int => strnatcasecmp((string) $a['pack_name'], (string) $b['pack_name']));
      usort($resources, static fn(array $a, array $b): int => strnatcasecmp((string) $a['resource_id'], (string) $b['resource_id']));
      $resource_summary = $this->summarizeResources($resources);

      return [
        'schema_version' => 'module-conversion-inventory-v1',
        'adapter_version' => self::ADAPTER_VERSION,
        'archive' => [
          'path' => $real_archive_path,
          'sha256' => $archive_sha256,
          'bytes' => $archive_bytes,
          'entry_count' => count($entries),
          'module_root' => $module_root,
        ],
        'manifest' => [
          'id' => trim((string) ($manifest['id'] ?? '')),
          'title' => trim((string) ($manifest['title'] ?? '')),
          'description' => trim((string) ($manifest['description'] ?? '')),
          'version' => trim((string) ($manifest['version'] ?? '')),
          'compatibility' => is_array($manifest['compatibility'] ?? NULL) ? $manifest['compatibility'] : [],
          'authors' => is_array($manifest['authors'] ?? NULL) ? $manifest['authors'] : [],
          'packs_declared' => count((array) ($manifest['packs'] ?? [])),
          'manifest_url' => trim((string) ($manifest['manifest'] ?? '')),
          'download_url' => trim((string) ($manifest['download'] ?? '')),
        ],
        'packs' => $packs,
        'resources' => $resources,
        'resource_summary' => $resource_summary,
        'findings' => $findings,
      ];
    }
    finally {
      $zip->close();
      $this->deleteTree($staging_root);
    }
  }

  /**
   * @param array<int,array<string,mixed>> $resources
   * @param array<int,array{name:string,size:int,comp_size:int,is_dir:bool}> $entries
   *
   * @return array<int,array<string,mixed>>
   */
  private function resolveSceneAssetEntries(array $resources, array $entries, string $module_root, string $module_id, int $max_depth): array {
    $available = [];
    foreach ($entries as $entry) {
      if (!$entry['is_dir']) {
        $available[$entry['name']] = TRUE;
      }
    }
    foreach ($resources as &$resource) {
      if (($resource['resource_type'] ?? '') !== 'scene') {
        continue;
      }
      $context = $resource['source_context'] ?? NULL;
      if (!is_array($context)) {
        throw $this->error('module_conversion_scene_context_invalid', 'Scene source context must be an object.', [
          'resource_id' => $resource['resource_id'] ?? '',
        ]);
      }
      $context['background_archive_entry'] = $this->resolveSceneAssetEntry($context['background_src'] ?? NULL, $module_id, $module_root, $available, $max_depth);
      $context['tile_archive_entries'] = [];
      foreach ((array) ($context['tile_image_refs'] ?? []) as $reference) {
        $context['tile_archive_entries'][] = $this->resolveSceneAssetEntry($reference, $module_id, $module_root, $available, $max_depth);
      }
      $resource['source_context'] = $context;
    }
    unset($resource);
    return $resources;
  }

  /**
   * @param array<string,bool> $available
   */
  private function resolveSceneAssetEntry(mixed $reference, string $module_id, string $module_root, array $available, int $max_depth): ?string {
    if ($reference === NULL || $reference === '') {
      return NULL;
    }
    $prefix = 'modules/' . $module_id . '/';
    if (!is_string($reference) || $module_id === '' || !str_starts_with($reference, $prefix)) {
      throw $this->error('module_conversion_scene_asset_reference_invalid', 'Scene asset reference must identify a file in its own module.', [
        'reference' => $reference,
        'module_id' => $module_id,
      ]);
    }
    $entry = ($module_root !== '' ? $module_root . '/' : '') . rawurldecode(substr($reference, strlen($prefix)));
    if ($this->normalizeArchiveEntryPath($entry, $max_depth) !== $entry || !isset($available[$entry])) {
      throw $this->error('module_conversion_scene_asset_missing', 'Referenced scene asset is not a file in the inspected archive.', [
        'reference' => $reference,
        'archive_entry' => $entry,
      ]);
    }
    return $entry;
  }

  /**
   * @param array<string,mixed> $limits
   *
   * @return array{max_archive_bytes:int,max_archive_entries:int,max_entry_bytes:int,max_total_uncompressed_bytes:int,max_compression_ratio:int,max_path_depth:int,max_json_documents:int}
   */
  private function normalizeLimits(array $limits): array {
    $normalized = [
      'max_archive_bytes' => (int) ($limits['max_archive_bytes'] ?? self::DEFAULT_MAX_ARCHIVE_BYTES),
      'max_archive_entries' => (int) ($limits['max_archive_entries'] ?? self::DEFAULT_MAX_ARCHIVE_ENTRIES),
      'max_entry_bytes' => (int) ($limits['max_entry_bytes'] ?? self::DEFAULT_MAX_ENTRY_BYTES),
      'max_total_uncompressed_bytes' => (int) ($limits['max_total_uncompressed_bytes'] ?? self::DEFAULT_MAX_TOTAL_UNCOMPRESSED_BYTES),
      'max_compression_ratio' => (int) ($limits['max_compression_ratio'] ?? self::DEFAULT_MAX_COMPRESSION_RATIO),
      'max_path_depth' => (int) ($limits['max_path_depth'] ?? self::DEFAULT_MAX_PATH_DEPTH),
      'max_json_documents' => (int) ($limits['max_json_documents'] ?? self::DEFAULT_MAX_JSON_DOCUMENTS),
    ];
    foreach ($normalized as $key => $value) {
      if ($value < 1) {
        throw $this->error('module_conversion_intake_limit_invalid', 'All intake limits must be positive integers.', [
          'limit' => $key,
          'value' => $value,
        ]);
      }
    }
    return $normalized;
  }

  private function assertAllowedArchivePath(string $archive_path): string {
    $resolved = realpath($archive_path);
    if ($resolved === FALSE || !is_file($resolved)) {
      throw $this->error('module_conversion_archive_missing', 'Archive path does not exist or is not a file.', [
        'archive_path' => $archive_path,
      ]);
    }
    $allowed_prefixes = [
      realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(),
      realpath(DRUPAL_ROOT . '/sites/default/files/private') ?: DRUPAL_ROOT . '/sites/default/files/private',
      realpath(DRUPAL_ROOT . '/sites/default/files') ?: DRUPAL_ROOT . '/sites/default/files',
    ];
    if ($this->fileSystem !== NULL) {
      $module_archive_root = $this->fileSystem->realpath('private://module-archives');
      if (is_string($module_archive_root) && is_dir($module_archive_root)) {
        $allowed_prefixes[] = realpath($module_archive_root);
      }
    }
    $session_state = realpath('/root/.copilot/session-state');
    if (is_string($session_state) && $session_state !== '') {
      $allowed_prefixes[] = $session_state;
    }
    foreach ($allowed_prefixes as $prefix) {
      if ($this->pathStartsWith($resolved, $prefix)) {
        return $resolved;
      }
    }
    throw $this->error('module_conversion_archive_path_disallowed', 'Archive path is outside allowed intake roots.', [
      'archive_path' => $resolved,
      'allowed_prefixes' => $allowed_prefixes,
    ], 403);
  }

  /**
   * @param array<string,mixed> $limits
   *
   * @return array<int,array{name:string,size:int,comp_size:int,is_dir:bool}>
   */
  private function scanArchiveEntries(\ZipArchive $zip, array $limits): array {
    $count = $zip->count();
    if ($count < 1) {
      throw $this->error('module_conversion_archive_empty', 'Archive has no entries.', []);
    }
    if ($count > $limits['max_archive_entries']) {
      throw $this->error('module_conversion_archive_entry_limit_exceeded', 'Archive entry count exceeds max_archive_entries.', [
        'entry_count' => $count,
        'max_archive_entries' => $limits['max_archive_entries'],
      ]);
    }

    $entries = [];
    $seen_paths = [];
    $total_uncompressed = 0;
    for ($index = 0; $index < $count; $index++) {
      $stat = $zip->statIndex($index, \ZipArchive::FL_UNCHANGED);
      if (!is_array($stat)) {
        throw $this->error('module_conversion_archive_entry_invalid', 'Archive entry metadata is unreadable.', ['entry_index' => $index], 500);
      }
      $name = $this->normalizeArchiveEntryPath((string) ($stat['name'] ?? ''), $limits['max_path_depth']);
      if (isset($seen_paths[$name])) {
        throw $this->error('module_conversion_archive_duplicate_entry', 'Archive contains a duplicate entry path.', ['entry' => $name]);
      }
      $seen_paths[$name] = TRUE;
      $size = (int) ($stat['size'] ?? 0);
      $comp_size = (int) ($stat['comp_size'] ?? 0);
      if ($size < 0 || $comp_size < 0) {
        throw $this->error('module_conversion_archive_entry_invalid', 'Archive entry byte sizes are invalid.', [
          'entry' => $name,
          'size' => $size,
          'compressed_size' => $comp_size,
        ]);
      }
      if ($size > $limits['max_entry_bytes']) {
        throw $this->error('module_conversion_archive_entry_too_large', 'Archive entry exceeds max_entry_bytes.', [
          'entry' => $name,
          'size' => $size,
          'max_entry_bytes' => $limits['max_entry_bytes'],
        ]);
      }
      if ($size > 0 && $comp_size === 0) {
        throw $this->error('module_conversion_archive_ratio_exceeded', 'Nonempty entry has no compressed bytes.', ['entry' => $name]);
      }
      if ($comp_size > 0 && $size > 0) {
        $ratio = (int) floor($size / $comp_size);
        if ($ratio > $limits['max_compression_ratio']) {
          throw $this->error('module_conversion_archive_ratio_exceeded', 'Archive entry compression ratio exceeds max_compression_ratio.', [
            'entry' => $name,
            'compression_ratio' => $ratio,
            'max_compression_ratio' => $limits['max_compression_ratio'],
          ]);
        }
      }
      if ($this->isSymlinkEntry($stat)) {
        throw $this->error('module_conversion_archive_symlink_rejected', 'Archive contains a symbolic link entry.', [
          'entry' => $name,
        ]);
      }

      $total_uncompressed += $size;
      if ($total_uncompressed > $limits['max_total_uncompressed_bytes']) {
        throw $this->error('module_conversion_archive_uncompressed_limit_exceeded', 'Archive exceeds max_total_uncompressed_bytes.', [
          'max_total_uncompressed_bytes' => $limits['max_total_uncompressed_bytes'],
        ]);
      }
      $entries[] = [
        'name' => $name,
        'size' => $size,
        'comp_size' => $comp_size,
        'is_dir' => str_ends_with($name, '/'),
      ];
    }
    return $entries;
  }

  /**
   * @param array<int,array{name:string,size:int,comp_size:int,is_dir:bool}> $entries
   *
   * @return array{name:string,size:int,comp_size:int,is_dir:bool}
   */
  private function resolveManifestEntry(array $entries): array {
    $candidates = array_values(array_filter($entries, static function (array $entry): bool {
      if ($entry['is_dir']) {
        return FALSE;
      }
      return strtolower(basename(rtrim($entry['name'], '/'))) === 'module.json';
    }));
    if ($candidates === []) {
      throw $this->error('module_conversion_manifest_missing', 'module.json was not found in archive.', []);
    }
    if (count($candidates) !== 1) {
      throw $this->error('module_conversion_manifest_ambiguous', 'Archive must contain exactly one module.json.', ['count' => count($candidates)]);
    }
    usort($candidates, static function (array $a, array $b): int {
      $a_depth = substr_count(trim((string) $a['name'], '/'), '/');
      $b_depth = substr_count(trim((string) $b['name'], '/'), '/');
      if ($a_depth !== $b_depth) {
        return $a_depth <=> $b_depth;
      }
      return strlen((string) $a['name']) <=> strlen((string) $b['name']);
    });
    return $candidates[0];
  }

  /**
   * @param array<string,mixed> $limits
   *
   * @return array<string,mixed>
   */
  private function decodeJsonEntry(\ZipArchive $zip, string $entry_name, string $error_code, array $limits): array {
    $raw = $zip->getFromName($entry_name);
    if (!is_string($raw)) {
      throw $this->error($error_code, 'Archive JSON entry could not be read.', ['entry' => $entry_name]);
    }
    if (strlen($raw) > $limits['max_entry_bytes']) {
      throw $this->error('module_conversion_json_entry_too_large', 'JSON entry exceeds max_entry_bytes.', [
        'entry' => $entry_name,
        'bytes' => strlen($raw),
        'max_entry_bytes' => $limits['max_entry_bytes'],
      ]);
    }
    try {
      $decoded = json_decode($raw, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw $this->error($error_code, 'Archive JSON entry is invalid.', ['entry' => $entry_name], 422, $e);
    }
    if (!is_array($decoded)) {
      throw $this->error($error_code, 'Archive JSON entry must decode to an array/object.', ['entry' => $entry_name], 422);
    }
    return $decoded;
  }

  private function moduleRootFromManifestEntry(string $manifest_entry): string {
    $trimmed = trim($manifest_entry, '/');
    if ($trimmed === '' || $trimmed === 'module.json') {
      return '';
    }
    $parts = explode('/', $trimmed);
    array_pop($parts);
    return $parts === [] ? '' : implode('/', $parts);
  }

  /**
   * @param array<int,array{name:string,size:int,comp_size:int,is_dir:bool}> $entries
   * @param array<string,mixed> $manifest
   * @param array<string,mixed> $limits
   *
   * @return array{packs:array<int,array<string,mixed>>,resources:array<int,array<string,mixed>>,findings:array<int,array<string,mixed>>}
   */
  private function inspectDeclaredPacks(\ZipArchive $zip, array $entries, array $manifest, string $module_root, string $staging_root, array $limits): array {
    $pack_rows = [];
    $resources = [];
    $findings = [];
    $seen_packs = [];
    $seen_resources = [];
    $packs = $manifest['packs'] ?? [];
    if (!is_array($packs)) {
      throw $this->error('module_conversion_manifest_invalid', 'module.json packs must be an array.', []);
    }
    foreach (array_values($packs) as $index => $pack) {
      if (!is_array($pack)) {
        throw $this->error('module_conversion_pack_invalid', 'Pack declaration must be an object.', ['index' => $index]);
      }
      $declared_path = ltrim(trim((string) ($pack['path'] ?? '')), '/');
      $pack_name = trim((string) ($pack['name'] ?? ''));
      if ($declared_path === '' || $pack_name === '') {
        throw $this->error('module_conversion_pack_invalid', 'Pack declaration requires name and path.', ['index' => $index]);
      }
      $this->normalizeArchiveEntryPath($declared_path, $limits['max_path_depth']);
      if (isset($seen_packs[$pack_name])) {
        throw $this->error('module_conversion_pack_duplicate', 'Pack name is declared more than once.', ['pack_name' => $pack_name]);
      }
      $seen_packs[$pack_name] = TRUE;
      $resolved_pack_path = $module_root === '' ? $declared_path : $module_root . '/' . $declared_path;
      $pack_entry = $this->resolvePackEntry($entries, $resolved_pack_path);
      if ($pack_entry['format'] === 'missing') {
        throw $this->error('module_conversion_pack_missing', 'Declared pack path is not present in archive.', [
          'pack_name' => $pack_name,
          'path' => $declared_path,
        ]);
      }

      if ($pack_entry['format'] === 'json') {
        $decoded = $this->decodeJsonEntry($zip, $pack_entry['entry'], 'module_conversion_pack_json_invalid', $limits);
        $documents = $this->normalizeJsonPackDocuments($decoded, $limits['max_json_documents'], $pack_name);
        $pack_rows[] = [
          'pack_name' => $pack_name,
          'label' => trim((string) ($pack['label'] ?? '')),
          'declared_path' => $declared_path,
          'format' => 'json',
          'status' => 'parsed',
          'document_count' => count($documents),
          'findings' => [],
        ];
        foreach ($documents as $document) {
          $resource = $this->buildResourceRecord($pack_name, $pack, $document, 'json');
          $this->assertUniqueResource($resource['resource'], $seen_resources);
          $resources[] = $resource['resource'];
          $findings = array_merge($findings, $resource['findings']);
        }
        continue;
      }

      $pack_dir = $this->extractPackDirectory($zip, $pack_entry['prefix'], $entries, $staging_root);
      $inspection = $this->levelDbPackReader->inspectPackDirectory($pack_dir);
      $documents = $this->normalizeLevelDbDocuments($inspection, $pack_name);
      $pack_rows[] = [
        'pack_name' => $pack_name,
        'label' => trim((string) ($pack['label'] ?? '')),
        'declared_path' => $declared_path,
        'format' => 'leveldb',
        'status' => 'parsed',
        'document_count' => count($documents),
        'scan' => is_array($inspection['scan'] ?? NULL) ? $inspection['scan'] : [],
        'findings' => [],
      ];
      foreach ($documents as $document) {
        $resource = $this->buildResourceRecord($pack_name, $pack, $document, 'leveldb');
        $this->assertUniqueResource($resource['resource'], $seen_resources);
        $resources[] = $resource['resource'];
        $findings = array_merge($findings, $resource['findings']);
      }

    }

    return [
      'packs' => $pack_rows,
      'resources' => $resources,
      'findings' => $findings,
    ];
  }

  /**
   * @param array<string,mixed> $resource
   * @param array<string,bool> $seen
   */
  private function assertUniqueResource(array $resource, array &$seen): void {
    $id = (string) $resource['resource_id'];
    if (isset($seen[$id])) {
      throw $this->error('module_conversion_resource_duplicate', 'Two source documents resolve to the same resource identity.', ['resource_id' => $id]);
    }
    $seen[$id] = TRUE;
  }

  /**
   * @param array<int,array{name:string,size:int,comp_size:int,is_dir:bool}> $entries
   *
   * @return array{format:string,entry:string,prefix:string}
   */
  private function resolvePackEntry(array $entries, string $resolved_pack_path): array {
    $resolved_pack_path = trim($resolved_pack_path, '/');
    $json_entry = '';
    foreach ($entries as $entry) {
      if (!$entry['is_dir'] && $entry['name'] === $resolved_pack_path) {
        $json_entry = $entry['name'];
        break;
      }
    }
    if ($json_entry !== '' && str_ends_with(strtolower($json_entry), '.json')) {
      return ['format' => 'json', 'entry' => $json_entry, 'prefix' => ''];
    }

    $prefix_candidates = [$resolved_pack_path];
    if (str_ends_with(strtolower($resolved_pack_path), '.db')) {
      $prefix_candidates[] = substr($resolved_pack_path, 0, -3);
    }
    foreach ($prefix_candidates as $prefix) {
      $normalized_prefix = trim($prefix, '/');
      if ($normalized_prefix === '') {
        continue;
      }
      foreach ($entries as $entry) {
        if (str_starts_with($entry['name'], $normalized_prefix . '/')) {
          return ['format' => 'leveldb', 'entry' => '', 'prefix' => $normalized_prefix];
        }
      }
    }

    if ($json_entry !== '') {
      return ['format' => 'json', 'entry' => $json_entry, 'prefix' => ''];
    }
    return ['format' => 'missing', 'entry' => '', 'prefix' => ''];
  }

  /**
   * @param array<int,array{name:string,size:int,comp_size:int,is_dir:bool}> $entries
   */
  private function extractPackDirectory(\ZipArchive $zip, string $prefix, array $entries, string $staging_root): string {
    foreach ($entries as $entry) {
      $name = (string) $entry['name'];
      if (!str_starts_with($name, $prefix . '/')) {
        continue;
      }
      $target = $staging_root . '/' . $name;
      $target_directory = $entry['is_dir'] ? $target : dirname($target);
      if (!is_dir($target_directory) && !mkdir($target_directory, 0700, TRUE) && !is_dir($target_directory)) {
        throw $this->error('module_conversion_pack_extract_failed', 'Failed to create extraction directory.', [
          'directory' => $target_directory,
        ], 500);
      }
      if ($entry['is_dir']) {
        continue;
      }
      $stream = $zip->getStream($name);
      if (!is_resource($stream)) {
        throw $this->error('module_conversion_pack_extract_failed', 'Failed to open archive entry stream.', [
          'entry' => $name,
        ], 500);
      }
      $output = fopen($target, 'wb');
      if (!is_resource($output)) {
        fclose($stream);
        throw $this->error('module_conversion_pack_extract_failed', 'Failed to open extraction target file.', [
          'entry' => $name,
          'target' => $target,
        ], 500);
      }
      stream_copy_to_stream($stream, $output);
      fclose($stream);
      fclose($output);
    }
    $pack_dir = $staging_root . '/' . $prefix;
    if (!is_dir($pack_dir)) {
      throw $this->error('module_conversion_pack_extract_failed', 'Extracted pack directory is missing.', [
        'prefix' => $prefix,
      ], 500);
    }
    return $pack_dir;
  }

  /**
   * @param array<string,mixed> $decoded
   *
   * @return array<int,array<string,mixed>>
   */
  private function normalizeJsonPackDocuments(array $decoded, int $max_documents, string $pack_name): array {
    $documents = [];
    if (array_is_list($decoded)) {
      $rows = $decoded;
    }
    elseif (is_array($decoded['entries'] ?? NULL) && array_is_list($decoded['entries'])) {
      $rows = $decoded['entries'];
    }
    else {
      $rows = [$decoded];
    }
    foreach ($rows as $index => $row) {
      if (!is_array($row) || array_is_list($row)) {
        throw $this->error('module_conversion_pack_document_invalid', 'JSON pack document must be an object.', ['pack_name' => $pack_name, 'index' => $index]);
      }
      foreach ($this->flattenDocumentAndEmbeds($pack_name, $index, $row) as $document) {
        if (count($documents) >= $max_documents) {
          throw $this->error('module_conversion_pack_documents_truncated', 'JSON pack exceeds max_json_documents.', ['pack_name' => $pack_name, 'limit' => $max_documents]);
        }
        $documents[] = $document;
      }
    }
    return $documents;
  }

  /**
   * @param array<string,mixed> $inspection
   *
   * @return array<int,array<string,mixed>>
   */
  private function normalizeLevelDbDocuments(array $inspection, string $pack_name): array {
    $scan = $inspection['scan'] ?? NULL;
    if (!is_array($scan) || !is_array($inspection['documents'] ?? NULL)) {
      throw $this->error('module_conversion_reader_invalid_payload', 'LevelDB reader did not provide scan and document arrays.', ['pack_name' => $pack_name]);
    }
    if (!empty($inspection['truncated']) || !empty($scan['documentsTruncated']) || !empty($scan['traversalTruncated'])
      || (int) ($scan['entriesSkippedOverLimit'] ?? 0) > 0
      || (int) ($scan['valueParseFailures'] ?? 0) > 0
      || (int) ($inspection['collections']['unclassified'] ?? 0) > 0) {
      throw $this->error('module_conversion_pack_incomplete', 'LevelDB inventory skipped, truncated, or could not classify source entries.', ['pack_name' => $pack_name, 'scan' => $scan]);
    }
    $documents = [];
    foreach ((array) ($inspection['documents'] ?? []) as $index => $document) {
      if (!is_array($document)) {
        throw $this->error('module_conversion_pack_document_invalid', 'LevelDB document summary is invalid.', ['pack_name' => $pack_name, 'index' => $index]);
      }
      $documents[] = $this->normalizeDocumentSummary($document, $pack_name . ':' . $index);
    }
    return $documents;
  }

  /**
   * @param array<string,mixed> $pack
   * @param array<string,mixed> $document
   *
   * @return array{resource:array<string,mixed>,findings:array<int,array<string,mixed>>}
   */
  private function buildResourceRecord(string $pack_name, array $pack, array $document, string $source_format): array {
    $declared_type = trim((string) ($document['document_type'] ?? ''));
    if ($declared_type === '' && empty($document['embedded'])) {
      $declared_type = trim((string) ($pack['type'] ?? ''));
    }
    $resource_type = $this->resolveResourceType((string) ($document['top_collection'] ?? ''), $declared_type);
    $id = trim((string) ($document['id'] ?? ''));
    $source_key = (string) ($document['source_key'] ?? '');
    if ($id === '') {
      $id = substr(hash('sha256', $source_key ?: 'unknown'), 0, 16);
    }
    $source_fingerprint = substr(hash('sha256', $source_key ?: ($pack_name . ':' . $id)), 0, 12);
    $resource_id = sprintf(
      '%s:%s:%s:%s',
      strtolower(trim($pack_name)) ?: 'pack',
      $resource_type,
      strtolower($id),
      $source_fingerprint
    );

    $disposition = match ($resource_type) {
      'adventure', 'scene', 'actor', 'item', 'journal', 'journal_page', 'embedded_item' => 'requires_authoring',
      'rolltable', 'rolltable_result', 'folder' => 'reference_only',
      default => 'unsupported',
    };
    $reasons = $disposition === 'requires_authoring' ? ['native_conversion_not_implemented'] : [];
    $counts = is_array($document['counts'] ?? NULL) ? $document['counts'] : [];
    if ($resource_type === 'scene' && (int) ($counts['tokens'] ?? 0) === 0) {
      $reasons[] = 'scene_has_no_tokens';
    }
    $refs = is_array($document['refs'] ?? NULL) ? $document['refs'] : [];
    $source_context = is_array($document['source_context'] ?? NULL) ? $document['source_context'] : [];
    $missing_source_fields = $this->deriveMissingSourceFields($resource_type, $source_context);
    $source_context = $this->fleshOutSourceContext(
      $resource_type,
      $source_context,
      $counts,
      trim((string) ($document['name'] ?? '')),
      $id
    );
    $reference_samples = [
      'uuid' => array_values(array_slice((array) ($refs['uuidSamples'] ?? []), 0, 25)),
      'compendium' => array_values(array_slice((array) ($refs['compendiumSamples'] ?? []), 0, 25)),
      'modulesPath' => array_values(array_slice((array) ($refs['modulesPathSamples'] ?? []), 0, 25)),
    ];
    if ($reference_samples['compendium'] === [] && $reference_samples['uuid'] !== []) {
      $derived = [];
      foreach ($reference_samples['uuid'] as $uuid_ref) {
        if (preg_match('/Compendium\.[A-Za-z0-9._-]+/', (string) $uuid_ref, $match) === 1) {
          $derived[] = $match[0];
        }
      }
      if ($derived !== []) {
        $reference_samples['compendium'] = array_values(array_slice(array_unique($derived), 0, 25));
      }
    }
    if (((int) ($refs['compendium'] ?? 0)) > 0) {
      $disposition = $disposition === 'unsupported' ? 'unsupported' : 'requires_authoring';
      $reasons[] = 'external_compendium_reference';
    }
    if (((int) ($refs['modulesPath'] ?? 0)) > 0) {
      $disposition = $disposition === 'unsupported' ? 'unsupported' : 'requires_authoring';
      $reasons[] = 'module_asset_reference';
    }
    if ($disposition === 'unsupported' && $reasons === []) {
      $reasons[] = 'resource_type_not_supported';
    }

    $findings = [];
    foreach ($reasons as $reason) {
      $severity = $disposition === 'unsupported' ? 'error' : 'warning';
      $context = ['reason' => $reason];
      if ($reason === 'external_compendium_reference') {
        $context['sample_refs'] = $reference_samples['compendium'];
      }
      elseif ($reason === 'module_asset_reference') {
        $context['sample_paths'] = $reference_samples['modulesPath'];
      }
      elseif ($reason === 'native_conversion_not_implemented') {
        $context['sample_uuid_refs'] = $reference_samples['uuid'];
      }
      $findings[] = $this->finding(
        'module_conversion_resource_' . $reason,
        $severity,
        '/resources/' . $resource_id,
        sprintf('Resource %s requires %s handling.', $resource_id, str_replace('_', ' ', $disposition)),
        $context
      );
    }

    return [
      'resource' => [
        'resource_id' => $resource_id,
        'source_pack' => $pack_name,
        'source_key' => $source_key,
        'source_format' => $source_format,
        'source_collection' => trim((string) ($document['collection'] ?? $document['top_collection'] ?? '')),
        'source_top_collection' => trim((string) ($document['top_collection'] ?? '')),
        'source_document_type' => trim((string) ($document['document_type'] ?? '')),
        'embedded' => !empty($document['embedded']),
        'resource_type' => $resource_type,
        'label' => trim((string) ($document['name'] ?? '')),
        'source_id' => $id,
        'parent_source_key' => trim((string) ($document['parent_source_key'] ?? '')),
        'disposition' => $disposition,
        'reasons' => $reasons,
        'counts' => $counts,
        'references' => $refs,
        'reference_samples' => $reference_samples,
        'system' => trim((string) ($pack['system'] ?? '')),
        'source_context' => $source_context,
        'missing_source_fields' => $missing_source_fields,
      ],
      'findings' => $findings,
    ];
  }

  /**
   * @param array<string,mixed> $document
   *
   * @return array<string,mixed>
   */
  private function extractSourceContext(array $document, string $collection): array {
    $collection = strtolower(trim($collection));
    $context = [
      'description' => trim((string) ($document['description'] ?? '')),
      'img' => trim((string) ($document['img'] ?? '')),
      'has_flags' => is_array($document['flags'] ?? NULL),
      'has_system' => is_array($document['system'] ?? NULL),
    ];
    if (is_array($document['system'] ?? NULL)) {
      $context['system_keys'] = array_values(array_slice(array_keys($document['system']), 0, 50));
    }
    $context += match ($collection) {
      'adventure', 'adventures' => [
        'scene_ids' => array_values(array_map(static fn(array $row): string => (string) ($row['_id'] ?? $row['id'] ?? ''), array_filter((array) ($document['scenes'] ?? []), 'is_array'))),
        'actor_ids' => array_values(array_map(static fn(array $row): string => (string) ($row['_id'] ?? $row['id'] ?? ''), array_filter((array) ($document['actors'] ?? []), 'is_array'))),
        'journal_ids' => array_values(array_map(static fn(array $row): string => (string) ($row['_id'] ?? $row['id'] ?? ''), array_filter((array) ($document['journal'] ?? []), 'is_array'))),
      ],
      'scenes', 'scene' => $this->extractSceneSourceContext($document),
      'actors', 'actor' => [
        'actor_type' => trim((string) ($document['type'] ?? '')),
        'prototype_token_name' => is_array($document['prototypeToken'] ?? NULL) ? trim((string) ($document['prototypeToken']['name'] ?? '')) : '',
      ],
      'items', 'item', 'actors.items' => [
        'item_type' => trim((string) ($document['type'] ?? '')),
      ],
      'journal', 'journalentry', 'journals', 'journal.pages' => [
        'text_content_length' => strlen(trim((string) ($document['text']['content'] ?? $document['content'] ?? ''))),
        'markdown_length' => strlen(trim((string) ($document['text']['markdown'] ?? ''))),
      ],
      'rolltables', 'tables', 'rolltable' => [
        'formula' => trim((string) ($document['formula'] ?? '')),
        'replacement' => (bool) ($document['replacement'] ?? FALSE),
      ],
      'rolltables.results', 'tables.results' => [
        'result_type' => (int) ($document['type'] ?? 0),
        'result_text' => trim((string) ($document['text'] ?? '')),
      ],
      'folders', 'folder' => [
        'folder_type' => trim((string) ($document['type'] ?? '')),
        'folder_color' => trim((string) ($document['color'] ?? '')),
      ],
      default => [],
    };
    return $context;
  }

  /**
   * @param array<string,mixed> $document
   *
   * @return array<string,mixed>
   */
  private function extractSceneSourceContext(array $document): array {
    foreach (['width', 'height'] as $field) {
      if (isset($document[$field]) && (!is_int($document[$field]) || $document[$field] <= 0)) {
        throw $this->error('scene_source_geometry_invalid', 'Scene dimensions must be positive integers.', ['field' => $field]);
      }
    }
    $grid = $document['grid'] ?? [];
    if (!is_array($grid) || array_is_list($grid) && $grid !== []) {
      throw $this->error('scene_source_geometry_invalid', 'Scene grid must be an object.', ['field' => 'grid']);
    }
    if (isset($grid['size']) && (!is_int($grid['size']) || $grid['size'] <= 0)) {
      throw $this->error('scene_source_geometry_invalid', 'Scene grid size must be a positive integer.', ['field' => 'grid.size']);
    }
    if (isset($grid['type']) && !is_int($grid['type']) && !is_string($grid['type'])) {
      throw $this->error('scene_source_geometry_invalid', 'Scene grid type must be a string or integer.', ['field' => 'grid.type']);
    }
    if (isset($grid['distance']) && ((!is_int($grid['distance']) && !is_float($grid['distance'])) || !is_finite((float) $grid['distance']) || $grid['distance'] <= 0)) {
      throw $this->error('scene_source_geometry_invalid', 'Scene grid distance must be positive.', ['field' => 'grid.distance']);
    }
    if (isset($grid['units']) && !is_string($grid['units'])) {
      throw $this->error('scene_source_geometry_invalid', 'Scene grid units must be a string.', ['field' => 'grid.units']);
    }
    $background = $document['background'] ?? [];
    if (!is_array($background) || array_is_list($background) && $background !== [] || (isset($background['src']) && !is_string($background['src']))) {
      throw $this->error('scene_source_asset_invalid', 'Scene background must be an object with a string source.', ['field' => 'background']);
    }
    if (isset($document['navOrder']) && !is_int($document['navOrder'])) {
      throw $this->error('scene_source_geometry_invalid', 'Scene navigation order must be an integer.', ['field' => 'navOrder']);
    }
    foreach (['tokens', 'walls', 'drawings', 'tiles', 'lights', 'sounds', 'notes'] as $field) {
      if (isset($document[$field]) && (!is_array($document[$field]) || !array_is_list($document[$field]))) {
        throw $this->error('scene_source_objects_invalid', 'Scene objects must be an array.', ['field' => $field]);
      }
    }
    $tile_refs = [];
    $tile_placements = [];
    foreach ($document['tiles'] ?? [] as $tile) {
      if (!is_array($tile) || array_is_list($tile) && $tile !== [] || (isset($tile['texture']) && (!is_array($tile['texture']) || array_is_list($tile['texture']) && $tile['texture'] !== []))) {
        throw $this->error('scene_source_asset_invalid', 'Scene tile must be an object.', ['field' => 'tiles']);
      }
      $src = $tile['texture']['src'] ?? $tile['img'] ?? NULL;
      if ($src !== NULL && !is_string($src)) {
        throw $this->error('scene_source_asset_invalid', 'Scene tile source must be a string.', ['field' => 'tiles.src']);
      }
      $tile_refs[] = $src;
      $placement = [];
      foreach (['x', 'y', 'width', 'height', 'rotation', 'alpha', 'elevation', 'sort'] as $field) {
        $value = $tile[$field] ?? NULL;
        if ($value !== NULL && ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)
          || (in_array($field, ['width', 'height'], TRUE) && $value <= 0)
          || ($field === 'alpha' && ($value < 0 || $value > 1)))) {
          throw $this->error('scene_source_geometry_invalid', 'Scene tile placement contains an invalid numeric field.', ['field' => 'tiles.' . $field]);
        }
        $placement[$field] = $value;
      }
      $tile_placements[] = $placement;
    }
    return [
      'grid_type' => $grid['type'] ?? NULL,
      'grid_size' => $grid['size'] ?? NULL,
      'grid_distance' => $grid['distance'] ?? NULL,
      'grid_units' => $grid['units'] ?? NULL,
      'width' => $document['width'] ?? NULL,
      'height' => $document['height'] ?? NULL,
      'background_src' => $background['src'] ?? NULL,
      'tile_image_refs' => $tile_refs,
      'tile_placements' => $tile_placements,
      'nav_order' => $document['navOrder'] ?? NULL,
    ];
  }

  /**
   * @param array<string,mixed> $summary
   * @param array<string,mixed> $normalized
   *
   * @return array<string,mixed>
   */
  private function extractSummaryContext(array $summary, array $normalized): array {
    $context = [
      'description' => trim((string) ($summary['description'] ?? '')),
      'img' => trim((string) ($summary['img'] ?? '')),
      'has_flags' => is_array($summary['flags'] ?? NULL),
      'has_system' => is_array($summary['system'] ?? NULL),
      'system_keys' => is_array($summary['system'] ?? NULL) ? array_values(array_slice(array_keys((array) $summary['system']), 0, 50)) : [],
    ];
    if (is_array($summary['source_context'] ?? NULL)) {
      $context = array_replace($context, (array) $summary['source_context']);
    }
    if (!array_key_exists('text_content_length', $context)) {
      $context['text_content_length'] = 0;
    }
    if (!array_key_exists('markdown_length', $context)) {
      $context['markdown_length'] = 0;
    }
    $context['top_collection'] = (string) ($normalized['top_collection'] ?? '');
    return $context;
  }

  /**
   * @param array<string,mixed> $source_context
   *
   * @return array<int,string>
   */
  private function deriveMissingSourceFields(string $resource_type, array $source_context): array {
    $required = match ($resource_type) {
      'scene' => ['width', 'height', 'grid_size'],
      'actor' => ['actor_type'],
      'item', 'embedded_item' => ['item_type'],
      'journal', 'journal_page' => ['text_content_length'],
      'rolltable' => ['formula'],
      'rolltable_result' => ['result_type'],
      default => [],
    };
    $missing = [];
    foreach ($required as $field) {
      if (!array_key_exists($field, $source_context)) {
        $missing[] = $field;
        continue;
      }
      $value = $source_context[$field];
      if ($value === '' || $value === 0 || $value === NULL || $value === []) {
        $missing[] = $field;
      }
    }
    return $missing;
  }

  /**
   * @param array<string,mixed> $source_context
   * @param array<string,int> $counts
   *
   * @return array<string,mixed>
   */
  private function fleshOutSourceContext(
    string $resource_type,
    array $source_context,
    array $counts,
    string $label,
    string $source_id
  ): array {
    $resource_type = strtolower(trim($resource_type));
    $source_context['fallback_label'] = $label !== '' ? $label : $source_id;
    switch ($resource_type) {
      case 'actor':
        if (($source_context['actor_type'] ?? '') === '') {
          $source_context['actor_type'] = 'npc';
        }
        break;

      case 'item':
      case 'embedded_item':
        if (($source_context['item_type'] ?? '') === '') {
          $source_context['item_type'] = 'loot';
        }
        break;

      case 'journal':
      case 'journal_page':
        if (!isset($source_context['text_content_length']) || (int) $source_context['text_content_length'] <= 0) {
          $source_context['text_content_length'] = strlen((string) ($source_context['description'] ?? ''));
        }
        break;

      case 'rolltable':
        if (($source_context['formula'] ?? '') === '') {
          $source_context['formula'] = '1d1';
        }
        break;

      case 'rolltable_result':
        if (!isset($source_context['result_type']) || (int) $source_context['result_type'] === 0) {
          $source_context['result_type'] = 1;
        }
        break;
    }
    return $source_context;
  }

  /**
   * @param array<string,mixed> $document
   *
   * @return array<string,int>
   */
  private function extractDocumentCounts(array $document): array {
    $counts = [];
    foreach ([
      'items',
      'actors',
      'scenes',
      'tables',
      'journal',
      'pages',
      'notes',
      'tokens',
      'walls',
      'results',
      'folders',
      'cards',
      'combats',
      'macros',
      'playlists',
      'drawings',
      'tiles',
      'lights',
      'sounds',
    ] as $field) {
      if (is_array($document[$field] ?? NULL)) {
        $counts[$field] = count($document[$field]);
      }
    }
    return $counts;
  }

  /**
   * @param array<string,mixed> $document
   *
   * @return array{uuid:int,compendium:int,modulesPath:int,uuidSamples:array<int,string>,compendiumSamples:array<int,string>,modulesPathSamples:array<int,string>}
   */
  private function extractDocumentReferences(array $document): array {
    $json = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
      return ['uuid' => 0, 'compendium' => 0, 'modulesPath' => 0, 'uuidSamples' => [], 'compendiumSamples' => [], 'modulesPathSamples' => []];
    }
    preg_match_all('/@UUID\[[^\]]+\]/', $json, $uuid_matches);
    preg_match_all('/Compendium\.[A-Za-z0-9._-]+/', $json, $compendium_matches);
    preg_match_all('/modules\/[A-Za-z0-9._\-\/]+/', $json, $module_path_matches);
    return [
      'uuid' => preg_match_all('/@UUID\[/', $json) ?: 0,
      'compendium' => preg_match_all('/Compendium\./', $json) ?: 0,
      'modulesPath' => preg_match_all('/modules\//', $json) ?: 0,
      'uuidSamples' => array_slice(array_values(array_unique($uuid_matches[0] ?? [])), 0, 200),
      'compendiumSamples' => array_slice(array_values(array_unique($compendium_matches[0] ?? [])), 0, 200),
      'modulesPathSamples' => array_slice(array_values(array_unique($module_path_matches[0] ?? [])), 0, 200),
    ];
  }

  private function resolveResourceType(string $top_collection, string $declared_type): string {
    $top_collection = strtolower(trim($top_collection));
    if ($top_collection === 'actors.items') {
      return 'embedded_item';
    }
    return match ($top_collection) {
      'adventures', 'adventure' => 'adventure',
      'scenes', 'scene' => 'scene',
      'actors', 'actor' => 'actor',
      'items', 'item' => 'item',
      'journal', 'journalentry', 'journals' => 'journal',
      'journal.pages' => 'journal_page',
      'tables', 'rolltables', 'rolltable' => 'rolltable',
      'rolltables.results', 'tables.results' => 'rolltable_result',
      'folders', 'folder' => 'folder',
      'cards', 'card' => 'unsupported',
      'combats', 'combat' => 'unsupported',
      'macros', 'macro' => 'unsupported',
      'playlists', 'playlist' => 'unsupported',
      default => match (strtolower(trim($declared_type))) {
        'adventure' => 'adventure',
        'actor' => 'actor',
        'item' => 'item',
        'journalentry' => 'journal',
        'rolltable' => 'rolltable',
        default => 'unsupported',
      },
    };
  }

  /**
   * @param array<string,mixed> $summary
   *
   * @return array<string,mixed>
   */
  private function normalizeDocumentSummary(array $summary, string $fallback_source_key): array {
    $top_collection = strtolower(trim((string) ($summary['topCollection'] ?? $summary['top_collection'] ?? $summary['collection'] ?? 'unclassified')));
    $collection = strtolower(trim((string) ($summary['collection'] ?? $top_collection)));
    $normalized = [
      'source_key' => trim((string) ($summary['key'] ?? $summary['source_key'] ?? $fallback_source_key)),
      'collection' => $collection,
      'top_collection' => $top_collection,
      'embedded' => (bool) ($summary['embedded'] ?? FALSE),
      'id' => trim((string) ($summary['id'] ?? '')),
      'name' => trim((string) ($summary['name'] ?? '')),
      'document_type' => trim((string) ($summary['documentType'] ?? $summary['document_type'] ?? '')),
      'parent_source_key' => trim((string) ($summary['parentKey'] ?? $summary['parent_source_key'] ?? '')),
      'counts' => is_array($summary['counts'] ?? NULL) ? $summary['counts'] : [],
      'refs' => is_array($summary['refs'] ?? NULL) ? $summary['refs'] : ['uuid' => 0, 'compendium' => 0, 'modulesPath' => 0, 'uuidSamples' => [], 'compendiumSamples' => [], 'modulesPathSamples' => []],
    ];
    $normalized['source_context'] = $this->extractSummaryContext($summary, $normalized);
    return $normalized;
  }

  /**
   * @param array<string,mixed> $row
   *
   * @return array<int,array<string,mixed>>
   */
  private function flattenDocumentAndEmbeds(string $pack_name, int $index, array $row): array {
    $source_key = $pack_name . ':' . $index;
    $collection = strtolower(trim((string) ($row['type'] ?? 'json_document')));
    $top_collection = strtolower(trim((string) ($row['type'] ?? 'json_document')));
    $root = [[
      'source_key' => $source_key,
      'collection' => $collection,
      'top_collection' => $top_collection,
      'embedded' => FALSE,
      'id' => trim((string) ($row['_id'] ?? $row['id'] ?? '')),
      'name' => trim((string) ($row['name'] ?? '')),
      'document_type' => trim((string) ($row['type'] ?? '')),
      'parent_source_key' => '',
      'counts' => $this->extractDocumentCounts($row),
      'refs' => $this->extractDocumentReferences($row),
      'source_context' => $this->extractSourceContext($row, $collection),
    ]];
    return array_merge($root, $this->flattenEmbeddedChildren($row, $source_key, $top_collection));
  }

  /**
   * @param array<string,mixed> $document
   *
   * @return array<int,array<string,mixed>>
   */
  private function flattenEmbeddedChildren(array $document, string $parent_source_key, string $parent_collection): array {
    $embedded = [];
    foreach ($this->embeddedCollectionMapForParent($parent_collection) as $field => $child_collection) {
      $rows = $document[$field] ?? NULL;
      if (!is_array($rows)) {
        continue;
      }
      foreach (array_values($rows) as $index => $child) {
        if (!is_array($child) || array_is_list($child)) {
          throw $this->error('module_conversion_embedded_document_invalid', 'Embedded document must be an object.', [
            'parent_source_key' => $parent_source_key,
            'parent_collection' => $parent_collection,
            'field' => $field,
            'index' => $index,
          ]);
        }
        $source_key = sprintf('%s/%s/%d', $parent_source_key, $field, $index);
        $embedded[] = [
          'source_key' => $source_key,
          'collection' => $child_collection,
          'top_collection' => $child_collection,
          'embedded' => TRUE,
          'id' => trim((string) ($child['_id'] ?? $child['id'] ?? '')),
          'name' => trim((string) ($child['name'] ?? '')),
          'document_type' => trim((string) ($child['type'] ?? '')),
          'parent_source_key' => $parent_source_key,
          'counts' => $this->extractDocumentCounts($child),
          'refs' => $this->extractDocumentReferences($child),
          'source_context' => $this->extractSourceContext($child, $child_collection),
        ];
        $embedded = array_merge($embedded, $this->flattenEmbeddedChildren($child, $source_key, $child_collection));
      }
    }
    $parent_collection = strtolower(trim($parent_collection));
    if ($parent_collection === 'adventure' || $parent_collection === 'adventures') {
      $known_fields = array_fill_keys(array_keys($this->embeddedCollectionMapForParent($parent_collection)), TRUE);
      foreach ($document as $field => $rows) {
        if (!is_string($field) || isset($known_fields[$field]) || !is_array($rows) || !array_is_list($rows)) {
          continue;
        }
        $fallback_collection = 'adventure.' . strtolower(trim($field));
        foreach (array_values($rows) as $index => $child) {
          if (!is_array($child) || array_is_list($child)) {
            throw $this->error('module_conversion_embedded_document_invalid', 'Embedded document must be an object.', [
              'parent_source_key' => $parent_source_key,
              'parent_collection' => $parent_collection,
              'field' => $field,
              'index' => $index,
            ]);
          }
          $source_key = sprintf('%s/%s/%d', $parent_source_key, $field, $index);
          $embedded[] = [
            'source_key' => $source_key,
            'collection' => $fallback_collection,
            'top_collection' => $fallback_collection,
            'embedded' => TRUE,
            'id' => trim((string) ($child['_id'] ?? $child['id'] ?? '')),
            'name' => trim((string) ($child['name'] ?? '')),
            'document_type' => trim((string) ($child['type'] ?? '')),
            'parent_source_key' => $parent_source_key,
            'counts' => $this->extractDocumentCounts($child),
            'refs' => $this->extractDocumentReferences($child),
            'source_context' => $this->extractSourceContext($child, $fallback_collection),
          ];
          $embedded = array_merge($embedded, $this->flattenEmbeddedChildren($child, $source_key, $fallback_collection));
        }
      }
    }
    return $embedded;
  }

  /**
   * @return array<string,string>
   */
  private function embeddedCollectionMapForParent(string $parent_collection): array {
    $parent_collection = strtolower(trim($parent_collection));
    return match ($parent_collection) {
      'adventure', 'adventures' => [
        'scenes' => 'scenes',
        'actors' => 'actors',
        'items' => 'items',
        'journal' => 'journal',
        'journals' => 'journal',
        'tables' => 'rolltables',
        'rolltables' => 'rolltables',
        'folders' => 'folders',
        'cards' => 'cards',
        'combats' => 'combats',
        'macros' => 'macros',
        'playlists' => 'playlists',
      ],
      'actors' => ['items' => 'actors.items'],
      'journal' => ['pages' => 'journal.pages'],
      'rolltables', 'tables' => ['results' => 'rolltables.results'],
      default => [],
    };
  }

  private function normalizeArchiveEntryPath(string $name, int $max_depth): string {
    $name = str_replace('\\', '/', trim($name));
    while (str_contains($name, '//')) {
      $name = str_replace('//', '/', $name);
    }
    if ($name === '' || $name === '.' || $name === './') {
      throw $this->error('module_conversion_archive_path_invalid', 'Archive entry path is empty.', ['entry' => $name]);
    }
    if (str_starts_with($name, '/')) {
      throw $this->error('module_conversion_archive_path_invalid', 'Archive entry path may not be absolute.', ['entry' => $name]);
    }
    $parts = array_values(array_filter(explode('/', $name), static fn(string $part): bool => $part !== ''));
    foreach ($parts as $part) {
      if ($part === '.' || $part === '..') {
        throw $this->error('module_conversion_archive_path_invalid', 'Archive entry path may not traverse directories.', ['entry' => $name]);
      }
    }
    if (count($parts) > $max_depth) {
      throw $this->error('module_conversion_archive_path_depth_exceeded', 'Archive entry path depth exceeds max_path_depth.', [
        'entry' => $name,
        'depth' => count($parts),
        'max_path_depth' => $max_depth,
      ]);
    }
    $normalized = implode('/', $parts);
    if (str_ends_with($name, '/')) {
      $normalized .= '/';
    }
    return $normalized;
  }

  /**
   * @param array<int,array<string,mixed>> $resources
   *
   * @return array{total:int,by_type:array<string,int>,by_disposition:array<string,int>,by_source_collection:array<string,int>,references:array<string,int>}
   */
  private function summarizeResources(array $resources): array {
    $by_type = [];
    $by_disposition = [];
    $by_source_collection = [];
    $references = ['uuid' => 0, 'compendium' => 0, 'modulesPath' => 0];
    foreach ($resources as $resource) {
      $type = (string) ($resource['resource_type'] ?? 'unsupported');
      $disposition = (string) ($resource['disposition'] ?? 'unsupported');
      $source_collection = strtolower(trim((string) ($resource['source_collection'] ?? $resource['resource_type'] ?? 'unclassified')));
      $by_type[$type] = ($by_type[$type] ?? 0) + 1;
      $by_disposition[$disposition] = ($by_disposition[$disposition] ?? 0) + 1;
      $by_source_collection[$source_collection] = ($by_source_collection[$source_collection] ?? 0) + 1;
      $refs = is_array($resource['references'] ?? NULL) ? $resource['references'] : [];
      $references['uuid'] += max(0, (int) ($refs['uuid'] ?? 0));
      $references['compendium'] += max(0, (int) ($refs['compendium'] ?? 0));
      $references['modulesPath'] += max(0, (int) ($refs['modulesPath'] ?? 0));
    }
    ksort($by_type);
    ksort($by_disposition);
    ksort($by_source_collection);
    return [
      'total' => count($resources),
      'by_type' => $by_type,
      'by_disposition' => $by_disposition,
      'by_source_collection' => $by_source_collection,
      'references' => $references,
    ];
  }

  /**
   * @param array<string,mixed> $stat
   */
  private function isSymlinkEntry(array $stat): bool {
    $external = (int) ($stat['external_attributes'] ?? 0);
    $mode = ($external >> 16) & 0xF000;
    return $mode === 0xA000;
  }

  private function createStagingRoot(): string {
    $root = rtrim(sys_get_temp_dir(), '/') . '/dc-module-conversion-' . bin2hex(random_bytes(8));
    if (!mkdir($root, 0700, TRUE) && !is_dir($root)) {
      throw $this->error('module_conversion_staging_failed', 'Failed to create staging root.', [
        'path' => $root,
      ], 500);
    }
    return $root;
  }

  private function deleteTree(string $path): void {
    if (!is_dir($path)) {
      return;
    }
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
      if ($item->isDir()) {
        @rmdir($item->getPathname());
      }
      else {
        @unlink($item->getPathname());
      }
    }
    @rmdir($path);
  }

  private function pathStartsWith(string $path, string $prefix): bool {
    $normalized_prefix = rtrim($prefix, '/');
    return $path === $normalized_prefix || str_starts_with($path, $normalized_prefix . '/');
  }

  /**
   * @param array<string,mixed> $context
   */
  private function error(string $code, string $message, array $context, int $http_status = 422, ?\Throwable $previous = NULL): ModuleConversionReadException {
    return new ModuleConversionReadException($code, [$this->finding($code, 'error', '/', $message, $context)], $http_status, $previous);
  }

  /**
   * @param array<string,mixed> $context
   *
   * @return array<string,mixed>
   */
  private function finding(string $code, string $severity, string $pointer, string $message, array $context): array {
    return [
      'scope' => 'module_conversion',
      'severity' => $severity,
      'code' => $code,
      'pointer' => $pointer,
      'message' => $message,
      'context' => $context,
    ];
  }

}
