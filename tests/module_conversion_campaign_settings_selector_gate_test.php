<?php

/**
 * Run with the site's Drush:
 *   ./vendor/bin/drush --no-interaction php:script tests/module_conversion_campaign_settings_selector_gate_test.php
 *
 * Campaign-settings selector gate:
 * - settings source-selector module options mirror launchable published sources
 * - apply-now with stale/non-launchable module release is rejected
 */

use Drupal\dungeoncrawler_content\Controller\CampaignSettingsController;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

$release_service = \Drupal::service('dungeoncrawler_content.module_conversion.release');
$launchable_sources = $release_service->listPublishedCampaignSources();
$selector_options = $release_service->campaignSourceSelectorOptions();
$expected_options = $selector_options['options'];

$transaction = \Drupal::database()->startTransaction();
try {
$campaign_initializer = \Drupal::service('dungeoncrawler_content.campaign_initialization');
$campaign_id = $launchable_sources !== []
  ? $campaign_initializer->initializeCampaign(
    1,
    sprintf('Settings Selector Gate %s', substr((string) $launchable_sources[0]['release_id'], 0, 8)),
    'classic_dungeon',
    'normal',
    [
      'kind' => 'module_release',
      'release_id' => (string) $launchable_sources[0]['release_id'],
    ]
  )
  : (int) \Drupal::database()->select('dc_campaigns', 'c')
    ->fields('c', ['id'])
    ->condition('uid', 1)
    ->orderBy('id', 'DESC')
    ->range(0, 1)
    ->execute()
    ->fetchField();
if ($campaign_id <= 0) {
  throw new \RuntimeException('No owner campaign available for settings selector gate.');
}

/** @var \Drupal\dungeoncrawler_content\Controller\CampaignSettingsController $controller */
$controller = \Drupal::classResolver()->getInstanceFromDefinition(CampaignSettingsController::class);
if (!$controller instanceof CampaignSettingsController) {
  throw new \RuntimeException('Failed to resolve CampaignSettingsController.');
}

$settings_response = $controller->getSettings($campaign_id);
$settings_payload = json_decode((string) $settings_response->getContent(), TRUE);
if (!is_array($settings_payload) || empty($settings_payload['success'])) {
  throw new \RuntimeException(sprintf('Campaign settings endpoint returned unexpected payload for campaign %d.', $campaign_id));
}
$source_selector = is_array($settings_payload['settings']['source_selector'] ?? NULL)
  ? $settings_payload['settings']['source_selector']
  : [];
$actual_options = is_array($source_selector['module_release_options'] ?? NULL)
  ? $source_selector['module_release_options']
  : [];

$expected_ids = array_values(array_keys($expected_options));
$actual_ids = array_values(array_keys($actual_options));
sort($expected_ids, SORT_STRING);
sort($actual_ids, SORT_STRING);
if ($expected_ids !== $actual_ids) {
  throw new \RuntimeException(sprintf(
    'Campaign-settings module_release option ids do not match launchable release ids. expected=%s actual=%s',
    json_encode($expected_ids, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    json_encode($actual_ids, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
  ));
}
foreach ($expected_options as $release_id => $label) {
  if (!isset($actual_options[$release_id]) || (string) $actual_options[$release_id] !== $label) {
    throw new \RuntimeException(sprintf('Campaign-settings module_release label mismatch for %s.', $release_id));
  }
  $unavailable_ids = (array) ($source_selector['module_release_unavailable_ids'] ?? []);
  sort($unavailable_ids, SORT_STRING);
  $expected_unavailable = $selector_options['unavailable_ids'];
  sort($expected_unavailable, SORT_STRING);
  if ($unavailable_ids !== $expected_unavailable) {
    throw new \RuntimeException('Settings selector must mark unlaunchable module options unavailable.');
  }
  $expected_dungeons = array_column(\Drupal::service('dungeoncrawler_content.dungeon_editor')->listLatestPublishedDungeons(), 'dungeon_id');
  $actual_dungeons = array_keys((array) ($source_selector['published_dungeon_options'] ?? []));
  sort($expected_dungeons, SORT_STRING);
  sort($actual_dungeons, SORT_STRING);
  if ($expected_dungeons !== $actual_dungeons) {
    throw new \RuntimeException('Settings published dungeon options must include only the latest published copy per title.');
  }
  $older_dungeon_id = '';
  foreach (\Drupal::service('dungeoncrawler_content.dungeon_editor')->listDungeons() as $dungeon) {
    if (($dungeon['publication_status'] ?? '') === 'published'
      && !in_array((string) $dungeon['dungeon_id'], $expected_dungeons, TRUE)) {
      $older_dungeon_id = (string) $dungeon['dungeon_id'];
      break;
    }
  }
}

$start_from_options = is_array($source_selector['start_from_options'] ?? NULL)
  ? $source_selector['start_from_options']
  : [];
if (!in_array('module_release', $start_from_options, TRUE)) {
  throw new \RuntimeException('Campaign-settings source selector is missing module_release start_from option.');
}
$default_module_release = trim((string) ($source_selector['defaults']['module_release'] ?? ''));
if ($default_module_release !== '' && !isset($actual_options[$default_module_release])) {
  throw new \RuntimeException(sprintf(
    'Campaign-settings default module_release (%s) is not present in selector options.',
    $default_module_release
  ));
}

$stale_release_id = trim((string) ($selector_options['unavailable_ids'][0] ?? ''));

if ($stale_release_id !== '' || $older_dungeon_id !== '') {
  $owner = User::load(1);
  if (!$owner) {
    throw new \RuntimeException('Unable to load uid=1 for campaign settings stale-release gate assertion.');
  }
  $account_switcher = \Drupal::service('account_switcher');
  $account_switcher->switchTo($owner);
  try {
    if ($older_dungeon_id !== '') {
      $older_request = Request::create(
        sprintf('/api/campaign/%d/settings/source', $campaign_id),
        'POST', [], [], [], [],
        json_encode([
          'start_from' => 'published_dungeon',
          'published_dungeon' => $older_dungeon_id,
          'theme' => 'classic_dungeon',
          'apply_now' => FALSE,
        ], JSON_THROW_ON_ERROR)
      );
      $older_response = $controller->setSource($campaign_id, $older_request);
      if ($older_response->getStatusCode() !== 409) {
        throw new \RuntimeException('Settings accepted an older same-title published dungeon.');
      }
    }
    if ($stale_release_id !== '') {
      $stale_request = Request::create(
        sprintf('/api/campaign/%d/settings/source', $campaign_id),
        'POST',
        [],
        [],
        [],
        [],
        json_encode([
          'start_from' => 'module_release',
          'module_release' => $stale_release_id,
          'theme' => 'classic_dungeon',
          'apply_now' => FALSE,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
      );
      $stale_response = $controller->setSource($campaign_id, $stale_request);
    }
  }
  finally {
    $account_switcher->switchBack();
  }
  if ($stale_release_id !== '' && $stale_response->getStatusCode() !== 409) {
    throw new \RuntimeException(sprintf(
      'Expected stale module_release selection to be rejected with 409; got %d.',
      $stale_response->getStatusCode()
    ));
  }
  if ($stale_release_id !== '') {
    $stale_payload = json_decode((string) $stale_response->getContent(), TRUE);
    if (!is_array($stale_payload) || empty($stale_payload['error'])) {
      throw new \RuntimeException('Expected stale module_release rejection payload to include error text.');
    }
    if (!str_contains(strtolower((string) $stale_payload['error']), 'not launchable')) {
      throw new \RuntimeException(sprintf('Unexpected stale module_release rejection message: %s', (string) $stale_payload['error']));
    }
  }
}
}
finally {
  $transaction->rollBack();
}

print "Campaign settings selector gate passed.\n";
print sprintf(
  "- campaign_id=%d launchable_sources=%d selector_options=%d stale_release_checked=%s\n",
  $campaign_id,
  count($launchable_sources),
  count($actual_options),
  $stale_release_id !== '' ? $stale_release_id : 'none'
);
