<?php

/**
 * Check the persisted Island survey launch without leaving a test campaign.
 *
 * Run with the site-local Drush and a 2 GB PHP limit.
 */

$audit_published = TRUE;
require __DIR__ . '/module_conversion_island_survey_release_audit_test.php';
foreach (array_keys($asset_urls) as $url) {
  echo "ASSET_URL {$url}\n";
}
