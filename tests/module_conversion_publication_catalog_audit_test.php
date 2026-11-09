<?php

/**
 * Run with the site's Drush:
 *   ./vendor/bin/drush --no-interaction php:script tests/module_conversion_publication_catalog_audit_test.php
 *
 * S5 publication/catalog audit gate:
 * - listed campaign sources must be launchable published releases
 * - each listed source must resolve to the same native dungeon/version pin
 * - published but non-launchable releases must not appear in launch catalog
 */

$releases = \Drupal::service('dungeoncrawler_content.module_conversion.release');
$summaries = $releases->listCampaignSourceSummaries(500);
$published_sources = $releases->listPublishedCampaignSources();
$source_ids = [];
$source_details = [];

foreach ($published_sources as $source) {
  $release_id = trim((string) ($source['release_id'] ?? ''));
  if ($release_id === '') {
    throw new \RuntimeException('Published campaign source is missing release_id.');
  }
  $module_id = trim((string) ($source['module_id'] ?? ''));
  $module_version = trim((string) ($source['module_version'] ?? ''));
  if ($module_id === '' || $module_version === '') {
    throw new \RuntimeException(sprintf('Published source %s is missing module identity fields.', $release_id));
  }
  if (str_contains($module_id, '--validation-') || str_contains($module_version, '--validation-') || str_starts_with($module_id, 'project-authored-gate-check')) {
    throw new \RuntimeException(sprintf('Published source %s leaked a validation/gate artifact identity (%s@%s).', $release_id, $module_id, $module_version));
  }
  if (($source['launchable'] ?? FALSE) !== TRUE) {
    throw new \RuntimeException(sprintf('Published source %s is listed but not launchable.', $release_id));
  }
  $resolved = $releases->resolveCampaignSource($release_id);
  $resolved_dungeon = trim((string) ($resolved['dungeon_id'] ?? ''));
  $resolved_version = trim((string) ($resolved['version_id'] ?? ''));
  $mapped_dungeon = trim((string) (($source['mapping']['dungeon_id'] ?? '')));
  $mapped_version = trim((string) (($source['mapping']['version_id'] ?? '')));
  if ($resolved_dungeon === '' || $resolved_version === '') {
    throw new \RuntimeException(sprintf('Resolved campaign source for %s is missing dungeon/version pin.', $release_id));
  }
  if ($resolved_dungeon !== $mapped_dungeon || $resolved_version !== $mapped_version) {
    throw new \RuntimeException(sprintf('Resolved source mismatch for %s (resolved %s/%s, mapped %s/%s).', $release_id, $resolved_dungeon, $resolved_version, $mapped_dungeon, $mapped_version));
  }
  $source_ids[$release_id] = TRUE;
  $source_details[] = [
    'release_id' => $release_id,
    'module_id' => $module_id,
    'module_version' => $module_version,
    'dungeon_id' => $resolved_dungeon,
    'version_id' => $resolved_version,
  ];
}

$published_count = 0;
$launchable_summary_count = 0;
foreach ($summaries as $summary) {
  if ((string) ($summary['publication_status'] ?? '') !== 'published') {
    continue;
  }
  $published_count++;
  $release_id = trim((string) ($summary['release_id'] ?? ''));
  $is_launchable = (($summary['launchable'] ?? FALSE) === TRUE);
  if ($is_launchable) {
    $launchable_summary_count++;
    if (!isset($source_ids[$release_id])) {
      throw new \RuntimeException(sprintf('Published launchable release %s is missing from campaign source listing.', $release_id));
    }
    continue;
  }
  if (isset($source_ids[$release_id])) {
    throw new \RuntimeException(sprintf('Published non-launchable release %s leaked into campaign source listing.', $release_id));
  }
}

print "Module conversion publication catalog audit gate passed.\n";
print sprintf(
  "- published_release_summaries=%d launchable_published_summaries=%d listed_campaign_sources=%d\n",
  $published_count,
  $launchable_summary_count,
  count($published_sources)
);
foreach ($source_details as $detail) {
  print sprintf(
    "- release=%s module=%s@%s dungeon=%s version=%s\n",
    $detail['release_id'],
    $detail['module_id'],
    $detail['module_version'],
    $detail['dungeon_id'],
    $detail['version_id']
  );
}
