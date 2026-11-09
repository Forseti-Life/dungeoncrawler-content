<?php

/**
 * Verify authorized Island scene maps and notices without retaining copies.
 *
 * Run with the site's Drush:
 *   ./vendor/bin/drush --no-interaction php:script tests/module_conversion_island_map_assets_validation_test.php
 */

$archive = '/root/.copilot/session-state/6d0c0ae4-1b32-4532-a93a-56ed72d05776/files/module-downloads/the-island-adventure-1.1.0.zip';
require_once __DIR__ . '/../tools/island_map_rights.php';
$notice = islandMapNotice();
$inventory = \Drupal::service('dungeoncrawler_content.module_conversion.archive_inspector')->inspectArchive($archive);
$staging = \Drupal::service('dungeoncrawler_content.module_conversion.scene_map_staging');
$files = \Drupal::service('file_system');
$stored = [];
$scene_count = 0;
$image_count = 0;
$transaction = \Drupal::database()->startTransaction();
try {
  foreach ($inventory['resources'] as $scene) {
    if (($scene['resource_type'] ?? NULL) !== 'scene') {
      continue;
    }
    $scene_id = (string) $scene['resource_id'];
    $context = $scene['source_context'];
    $rights_for = static fn(string $entry): array => islandMapRights($inventory, $scene_id, $entry, $notice);
    $attestations = [
      'background' => $rights_for((string) $context['background_archive_entry']),
      'tiles' => array_map($rights_for, (array) ($context['tile_archive_entries'] ?? [])),
    ];
    $result = $staging->stageSceneAssets($archive, $inventory, $scene_id, $attestations, 1);
    $stored = array_merge($stored, $result['stored']);
    if (count($result['maps']['tiles']) !== count($attestations['tiles'])) {
      throw new \RuntimeException('Island scene tile attestation coverage is incomplete.');
    }
    foreach ($result['stored'] as $image) {
      $path = $files->realpath($image['file_uri']);
      $license = $files->realpath($image['license_notice_uri']);
      if (!is_string($path) || !is_file($path) || hash_file('sha256', $path) !== $image['sha256']
        || !is_string($license) || !is_file($license) || file_get_contents($license) !== $notice) {
        throw new \RuntimeException('Staged Island image or adjacent license notice differs from attested source.');
      }
      $image_count++;
    }
    $scene_count++;
  }
  if ($scene_count !== 10 || $image_count !== 19) {
    throw new \RuntimeException('Island source-map coverage must include ten backgrounds and nine tile occurrences.');
  }
  echo "All 19 attested Island map images and adjacent MIT notices verified across ten scenes; trial copies removed.\n";
}
finally {
  try {
    $staging->discardSceneAssets($stored);
  }
  finally {
    $transaction->rollBack();
  }
}
