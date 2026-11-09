<?php

/**
 * Run with the site's Drush:
 *   ./vendor/bin/drush --no-interaction php:script tests/module_conversion_campaign_create_selector_gate_test.php
 *
 * Campaign-create selector gate:
 * - module-release selector options must exactly mirror launchable published sources
 * - stale/non-launchable published release selections must fail validation
 */

use Drupal\Core\Form\FormState;
use Drupal\dungeoncrawler_content\Form\CampaignCreateForm;

$release_service = \Drupal::service('dungeoncrawler_content.module_conversion.release');
$launchable_sources = $release_service->listPublishedCampaignSources();
$selector_options = $release_service->campaignSourceSelectorOptions();
$expected_options = $selector_options['options'];
$available_ids = array_column($launchable_sources, 'release_id');

/** @var \Drupal\dungeoncrawler_content\Form\CampaignCreateForm $form_object */
$form_object = \Drupal::classResolver()->getInstanceFromDefinition(CampaignCreateForm::class);
if (!$form_object instanceof CampaignCreateForm) {
  throw new \RuntimeException('Failed to resolve CampaignCreateForm.');
}

$form_state = new FormState();
$form = $form_object->buildForm([], $form_state);
$selector = (array) ($form['module_release'] ?? []);
$actual_options = (array) ($selector['#options'] ?? []);
$actual_ids = array_values(array_keys($actual_options));
$expected_ids = array_values(array_keys($expected_options));
sort($actual_ids, SORT_STRING);
sort($expected_ids, SORT_STRING);
if ($actual_ids !== $expected_ids) {
  throw new \RuntimeException(sprintf(
    'Campaign-create module_release option ids do not match launchable release ids. expected=%s actual=%s',
    json_encode($expected_ids, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    json_encode($actual_ids, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
  ));
}
foreach ($expected_options as $release_id => $label) {
  if (!isset($actual_options[$release_id]) || (string) $actual_options[$release_id] !== $label) {
    throw new \RuntimeException(sprintf('Campaign-create module_release label mismatch for %s.', $release_id));
  }
  foreach ($selector_options['unavailable_ids'] as $unavailable_id) {
    if (!isset($actual_options[$unavailable_id])
      || !str_contains((string) $actual_options[$unavailable_id], 'published, not playable:')
      || in_array($unavailable_id, $available_ids, TRUE)) {
      throw new \RuntimeException(sprintf('Unavailable release %s is not identified correctly in the form.', $unavailable_id));
    }
  }
  if ($available_ids === [] && (string) ($selector['#default_value'] ?? '') !== '') {
    throw new \RuntimeException('Form must not default to an unavailable module release.');
  }

  $dungeon_editor = \Drupal::service('dungeoncrawler_content.dungeon_editor');
  $latest_dungeons = $dungeon_editor->listLatestPublishedDungeons();
  $published_options = (array) ($form['published_dungeon']['#options'] ?? []);
  $latest_ids = array_column($latest_dungeons, 'dungeon_id');
  $actual_dungeon_ids = array_keys($published_options);
  sort($latest_ids, SORT_STRING);
  sort($actual_dungeon_ids, SORT_STRING);
  if ($actual_dungeon_ids !== $latest_ids) {
    throw new \RuntimeException('Campaign-create published dungeons must match the latest published copy per title.');
  }
  $seen_names = [];
  foreach ($latest_dungeons as $dungeon) {
    $name = mb_strtolower(trim((string) $dungeon['name']));
    if (isset($seen_names[$name])) {
      throw new \RuntimeException(sprintf('Campaign-create published dungeon title %s is duplicated.', $name));
    }
    $seen_names[$name] = TRUE;
  }
  $older_dungeon_id = '';
  foreach ($dungeon_editor->listDungeons() as $dungeon) {
    if (($dungeon['publication_status'] ?? '') === 'published'
      && !isset($published_options[(string) $dungeon['dungeon_id']])) {
      $older_dungeon_id = (string) $dungeon['dungeon_id'];
      break;
    }
  }
  if ($older_dungeon_id !== '') {
    $older_state = new FormState();
    $older_state->setValues([
      'start_from' => 'published_dungeon',
      'published_dungeon' => $older_dungeon_id,
      'theme' => 'classic_dungeon',
      'name' => 'Old dungeon selector check',
    ]);
    $form_object->validateForm($form, $older_state);
    if (!str_contains((string) ($older_state->getErrors()['published_dungeon'] ?? ''), 'no longer the latest')) {
      throw new \RuntimeException('Older same-title dungeon selection must be rejected.');
    }
  }
}

$start_from_options = (array) ($form['start_from']['#options'] ?? []);
if (!array_key_exists('module_release', $start_from_options)) {
  throw new \RuntimeException('Campaign-create start_from options are missing module_release.');
}

$stale_release_id = trim((string) ($selector_options['unavailable_ids'][0] ?? ''));

if ($stale_release_id !== '') {
  $stale_state = new FormState();
  $stale_state->setValues([
    'start_from' => 'module_release',
    'module_release' => $stale_release_id,
    'published_dungeon' => '',
    'theme' => 'classic_dungeon',
    'name' => 'Selector gate stale release check',
    'difficulty' => 'normal',
  ]);
  $form_object->validateForm($form, $stale_state);
  $errors = $stale_state->getErrors();
  $module_release_error = (string) ($errors['module_release'] ?? '');
  if ($module_release_error === '' || !str_contains(strtolower($module_release_error), 'not launchable')) {
    throw new \RuntimeException(sprintf('Expected stale module_release selection to be rejected as not launchable. release_id=%s', $stale_release_id));
  }
}

if ($available_ids !== []) {
  $valid_state = new FormState();
  $valid_state->setValues([
    'start_from' => 'module_release',
    'module_release' => $available_ids[0],
    'published_dungeon' => '',
    'theme' => 'classic_dungeon',
    'name' => 'Selector gate valid release check',
    'difficulty' => 'normal',
  ]);
  $form_object->validateForm($form, $valid_state);
  $errors = $valid_state->getErrors();
  if (isset($errors['module_release'])) {
    throw new \RuntimeException(sprintf('Expected launchable module_release %s to validate, but got module_release error: %s', $available_ids[0], (string) $errors['module_release']));
  }
}

print "Campaign create selector gate passed.\n";
print sprintf(
  "- launchable_sources=%d selector_options=%d stale_release_checked=%s\n",
  count($available_ids),
  count($actual_options),
  $stale_release_id !== '' ? $stale_release_id : 'none'
);
