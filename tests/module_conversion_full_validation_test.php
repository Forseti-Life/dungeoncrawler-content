<?php

/**
 * Run with the site's Drush:
 *   ./vendor/bin/drush --no-interaction php:script tests/module_conversion_full_validation_test.php
 * Set MODULE_CONVERSION_VALIDATION_ARCHIVE_DIR to validate a directory of
 * module ZIPs; the project-authored fixture is always included.
 *
 * Receipt-execution gate: every discovered module archive must execute with
 * zero failed required resources and auto-register immutable receipts.
 * Scene/quest publication coverage and player playability are separate gates.
 */

use Drupal\dungeoncrawler_content\Service\ModuleConversion\LevelDbPackReaderInterface;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionArchiveInspectorService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionPlanService;

function build_fixture_archive(string $fixture_directory): string {
  $real_fixture = realpath($fixture_directory);
  if (!is_string($real_fixture) || !is_dir($real_fixture)) {
    throw new \RuntimeException('Fixture directory missing: ' . $fixture_directory);
  }
  $archive = tempnam(sys_get_temp_dir(), 'module-fixture-full-');
  if ($archive === FALSE) {
    throw new \RuntimeException('Unable to create temp archive for fixture.');
  }
  $zip = new \ZipArchive();
  if ($zip->open($archive, \ZipArchive::OVERWRITE) !== TRUE) {
    throw new \RuntimeException('Unable to open temp archive for fixture.');
  }
  $iterator = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($real_fixture, \FilesystemIterator::SKIP_DOTS),
    \RecursiveIteratorIterator::LEAVES_ONLY
  );
  foreach ($iterator as $file) {
    if (!$file->isFile()) {
      continue;
    }
    $absolute = $file->getPathname();
    $relative = str_replace('\\', '/', substr($absolute, strlen($real_fixture) + 1));
    $raw = file_get_contents($absolute);
    if (!is_string($raw)) {
      throw new \RuntimeException('Unable to read fixture file: ' . $absolute);
    }
    $zip->addFromString($relative, $raw);
  }
  $zip->close();
  return $archive;
}

/**
 * @return array<int,string>
 */
function discover_module_archives(array &$temporary_archives): array {
  $archives = [];

  $configured_dir = getenv('MODULE_CONVERSION_VALIDATION_ARCHIVE_DIR');
  $archive_dir = is_string($configured_dir) && $configured_dir !== ''
    ? $configured_dir
    : '/root/.copilot/session-state/6d0c0ae4-1b32-4532-a93a-56ed72d05776/files/module-downloads';
  if (is_string($configured_dir) && $configured_dir !== '' && !is_dir($archive_dir)) {
    throw new \InvalidArgumentException('Configured module validation archive directory does not exist.');
  }
  if (is_dir($archive_dir)) {
    foreach ((array) glob($archive_dir . '/*.zip') as $zip_path) {
      if (is_string($zip_path) && is_file($zip_path)) {
        $archives[] = $zip_path;
      }
    }
  }

  $fixture_dir = __DIR__ . '/fixtures/module_conversion/forseti-lantern-conservatory';
  if (is_dir($fixture_dir)) {
    $fixture_archive = build_fixture_archive($fixture_dir);
    $temporary_archives[] = $fixture_archive;
    $archives[] = $fixture_archive;
  }

  $archives = array_values(array_unique($archives));
  sort($archives, SORT_STRING);
  return $archives;
}

$reader = \Drupal::service('dungeoncrawler_content.module_conversion.leveldb_pack_reader');
if (!$reader instanceof LevelDbPackReaderInterface) {
  throw new \RuntimeException('module_conversion leveldb reader service is unavailable.');
}
$inspector = new ModuleConversionArchiveInspectorService($reader);
$planner = new ModuleConversionPlanService();
$releases = \Drupal::service('dungeoncrawler_content.module_conversion.release');

$temporary_archives = [];
$archives = discover_module_archives($temporary_archives);
if ($archives === []) {
  throw new \RuntimeException('No module archives were discovered for full validation.');
}

$failures = [];
$results = [];
try {
foreach ($archives as $archive_path) {
  if (!is_file($archive_path)) {
    $failures[] = [
      'archive' => $archive_path,
      'reason' => 'archive_missing',
      'detail' => 'Archive path is not a file.',
    ];
    continue;
  }
  $transaction = \Drupal::database()->startTransaction();
  try {
    $inventory = $inspector->inspectArchive($archive_path);
    $inventory_for_validation = $inventory;
    $manifest_id = trim((string) ($inventory_for_validation['manifest']['id'] ?? 'module'));
    $manifest_version = trim((string) ($inventory_for_validation['manifest']['version'] ?? '0.0.0'));
    $validation_suffix = substr(hash('sha256', $archive_path . ':' . (string) ($inventory_for_validation['archive']['sha256'] ?? '') . ':' . (string) microtime(TRUE)), 0, 12);
    $inventory_for_validation['manifest']['id'] = $manifest_id . '--validation-' . $validation_suffix;
    $inventory_for_validation['manifest']['version'] = $manifest_version . '--validation-' . $validation_suffix;
    $plan = $planner->buildPlan($inventory_for_validation);
    $release = $releases->registerRelease($inventory_for_validation, $plan, NULL, 0);
    $run = $releases->executePlannedCommands((string) $release['release_id'], [
      'publish_version' => '1.0.0',
      'register_receipts' => TRUE,
    ], 0);
    $run_data = (array) ($run['run'] ?? []);
    $failed = (int) ($run_data['failed'] ?? 0);
    $succeeded = (int) ($run_data['succeeded'] ?? 0);
    $receipts_registered = (($run_data['receipts_registered'] ?? FALSE) === TRUE);
    $required_count = count((array) ($plan['required_receipt_resource_ids'] ?? []));
    if ($failed !== 0 || $succeeded !== $required_count || !$receipts_registered) {
      $failed_details = array_values(array_filter((array) ($run_data['details'] ?? []), static fn(array $detail): bool => (($detail['state'] ?? '') === 'failed')));
      $failed_details = array_slice($failed_details, 0, 5);
      $failures[] = [
        'archive' => $archive_path,
        'module_id' => (string) ($inventory['manifest']['id'] ?? ''),
        'module_version' => (string) ($inventory['manifest']['version'] ?? ''),
        'reason' => 'conversion_validation_incomplete',
        'detail' => sprintf(
          'required=%d succeeded=%d failed=%d receipts_registered=%s failed_samples=%s',
          $required_count,
          $succeeded,
          $failed,
          $receipts_registered ? 'true' : 'false',
          json_encode($failed_details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ),
      ];
    }
    $results[] = [
      'archive' => $archive_path,
      'module_id' => (string) ($inventory['manifest']['id'] ?? ''),
      'module_version' => (string) ($inventory['manifest']['version'] ?? ''),
      'required_resources' => $required_count,
      'succeeded' => $succeeded,
      'failed' => $failed,
      'receipts_registered' => $receipts_registered,
    ];
  }
  catch (\Throwable $e) {
    $failures[] = [
      'archive' => $archive_path,
      'reason' => 'exception',
      'detail' => $e->getMessage(),
    ];
  }
  finally {
    $transaction->rollBack();
  }
}

if ($failures !== []) {
  $lines = ["Module conversion receipt validation failed for one or more archives."];
  foreach ($failures as $failure) {
    $lines[] = sprintf(
      '- %s :: %s :: %s',
      (string) ($failure['archive'] ?? 'unknown'),
      (string) ($failure['reason'] ?? 'unknown'),
      (string) ($failure['detail'] ?? '')
    );
  }
  throw new \RuntimeException(implode("\n", $lines));
}

print "Module conversion receipt validation passed for all discovered archives; this is not publication or gameplay signoff.\n";
foreach ($results as $result) {
  print sprintf(
    "- %s@%s required=%d succeeded=%d failed=%d receipts_registered=%s\n",
    (string) ($result['module_id'] ?? 'unknown'),
    (string) ($result['module_version'] ?? 'unknown'),
    (int) ($result['required_resources'] ?? 0),
    (int) ($result['succeeded'] ?? 0),
    (int) ($result['failed'] ?? 0),
    (($result['receipts_registered'] ?? FALSE) === TRUE) ? 'true' : 'false'
  );
}
}
finally {
  foreach ($temporary_archives as $archive) {
    if (is_file($archive) && !unlink($archive)) {
      throw new \RuntimeException('Unable to remove temporary module validation archive: ' . $archive);
    }
  }
}
