<?php

/**
 * Run with the site's Drush:
 *   ./vendor/bin/drush --no-interaction php:script tests/module_conversion_playability_audit_test.php
 *
 * Published releases must place every converted scene in their pinned dungeon.
 */

$database = \Drupal::database();
$releases = \Drupal::service('dungeoncrawler_content.module_conversion.release');
$launchable_ids = array_fill_keys(array_map(
  static fn(array $source): string => (string) $source['release_id'],
  $releases->listPublishedCampaignSources()
), TRUE);
$rows = $database->select('dungeoncrawler_content_module_releases', 'r')
  ->fields('r', ['release_id', 'module_id', 'module_version'])
  ->condition('publication_status', 'published')
  ->condition('module_id', 'project-authored-gate-check%', 'NOT LIKE')
  ->condition('module_id', '%--validation-%', 'NOT LIKE')
  ->condition('module_version', '%--validation-%', 'NOT LIKE')
  ->execute()
  ->fetchAll(\PDO::FETCH_ASSOC);
if ($rows === []) {
  throw new \RuntimeException('No published real module releases available for playability audit.');
}

$verified_count = 0;
foreach ($rows as $row) {
  $coverage = $releases->sceneCoverageReport((string) $row['release_id']);
  $launchable = isset($launchable_ids[(string) $row['release_id']]);
  printf(
    "- %s@%s scene_rooms=%d placed_scene_rooms=%d launchable=%s\n",
    (string) $row['module_id'],
    (string) $row['module_version'],
    $coverage['scene_count'],
    $coverage['placed_count'],
    $launchable ? 'yes' : 'no'
  );
  if ($launchable && (!$coverage['complete'] || $coverage['scene_count'] === 0)) {
    throw new \RuntimeException(sprintf(
      'Uncovered release %s@%s is visible to players.',
      (string) $row['module_id'],
      (string) $row['module_version']
    ));
  }
  if ($launchable) {
    $verified_count++;
  }
}

if ($verified_count === 0) {
  throw new \RuntimeException('No real module release has verified scene coverage and a launchable dungeon; preserved legacy releases remain quarantined.');
}
printf("Module conversion scene coverage audit passed for %d launchable release(s); topology and runtime content require separate validation.\n", $verified_count);
