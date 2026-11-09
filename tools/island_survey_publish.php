<?php

/**
 * Verify the staged original Island survey under rollback, then publish it.
 *
 * Run once with the site-local Drush and a 2 GB PHP limit. The prerequisite
 * audit leaves no campaign or publication behind.
 */

require __DIR__ . '/../tests/module_conversion_island_survey_release_audit_test.php';

$published = $releases->registerPublishedManifest($release_id, $manifest, 1);
$source = $releases->resolveCampaignSource($release_id);
if (($published['publication_status'] ?? NULL) !== 'published'
  || ($source['dungeon_id'] ?? NULL) !== $manifest['dungeon_id']
  || ($source['version_id'] ?? NULL) !== $manifest['version_id']) {
  throw new \RuntimeException('The verified Island survey was not published to its exact canonical dungeon pin.');
}
if (!in_array($release_id, array_column($releases->listPublishedCampaignSources(), 'release_id'), TRUE)) {
  throw new \RuntimeException('The published Island survey is absent from module campaign sources.');
}
echo 'Published original Island survey release ' . $release_id
  . ' dungeon=' . $manifest['dungeon_id'] . '@' . $manifest['version_id'] . "\n";
