<?php

/**
 * Publish the audited original Island story under its exact canonical pins.
 *
 * Run once with the site-local Drush and a 2 GB PHP limit. The prerequisite
 * player audit rolls back its trial publication and campaign before this runs.
 */

require __DIR__ . '/../tests/module_conversion_island_original_story_audit_test.php';
if ($original_status !== 'receipts_recorded') {
  throw new \RuntimeException('The original-story release has already been published.');
}
$transaction = $database->startTransaction();
$verified = FALSE;
try {
  $published = $releases->registerPublishedManifest($release_id, $manifest, 1);
  $source = $releases->resolveCampaignSource($release_id);
  if (($published['publication_status'] ?? NULL) !== 'published'
    || ($source['dungeon_id'] ?? NULL) !== $manifest['dungeon_id']
    || ($source['version_id'] ?? NULL) !== $manifest['version_id']
    || !in_array($release_id, array_column($releases->listPublishedCampaignSources(), 'release_id'), TRUE)) {
    throw new \RuntimeException('The audited original story did not publish under its exact verified pins.');
  }
  $verified = TRUE;
}
finally {
  if (!$verified) {
    $transaction->rollBack();
  }
}
unset($transaction);
echo 'Published original Island story release ' . $release_id . ' dungeon='
  . $manifest['dungeon_id'] . '@' . $manifest['version_id'] . PHP_EOL;
