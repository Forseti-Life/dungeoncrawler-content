<?php

/**
 * Rollback-only launch audit for the persistent Island survey.
 *
 * Run with the site-local Drush and a 2 GB PHP limit. No campaign or release
 * publication created by this test survives it. Set $audit_published to
 * verify a previously published release without changing its status.
 */

$database = \Drupal::database();
$row = $database->select('dungeoncrawler_content_module_releases', 'r')
  ->fields('r', ['release_id', 'publication_status', 'mapping_data'])
  ->condition('module_id', 'the-island-adventure')
  ->condition('module_version', '1.1.0')
  ->condition('conversion_revision', 'island-survey-v1')
  ->range(0, 1)->execute()->fetchAssoc();
$expected_status = !empty($audit_published) ? 'published' : 'receipts_recorded';
if (!is_array($row) || $row['publication_status'] !== $expected_status) {
  throw new \RuntimeException('The reviewed Island survey has an unexpected status before launch audit.');
}
$release_id = (string) $row['release_id'];
$mapping = json_decode((string) $row['mapping_data'], TRUE, 512, JSON_THROW_ON_ERROR);
if (($mapping['native_receipt_summary']['recorded_count'] ?? NULL) !== 15532
  || count((array) ($mapping['native_receipts'] ?? [])) !== 15532) {
  throw new \RuntimeException('The survey revision lacks complete native receipts.');
}
$dungeon_receipts = array_values(array_filter($mapping['native_receipts'], static fn(array $receipt): bool => ($receipt['owner'] ?? NULL) === 'DungeonEditorService'));
if (count($dungeon_receipts) !== 1) {
  throw new \RuntimeException('The survey revision must pin exactly one dungeon.');
}
$dungeon_receipt = $dungeon_receipts[0];
$manifest = [
  'dungeon_id' => (string) $dungeon_receipt['target_id'],
  'version_id' => (string) $dungeon_receipt['target_version'],
  'receipt_resource_id' => (string) $dungeon_receipt['resource_id'],
];
if (($dungeon_receipt['source_receipt']['quest_template_id'] ?? NULL) !== 'island-survey-v1') {
  throw new \RuntimeException('The original survey dungeon must pin its exact quest template.');
}
$releases = \Drupal::service('dungeoncrawler_content.module_conversion.release');
$projector = \Drupal::service('dungeoncrawler_content.map_visual_state_projector');
$projection = new \ReflectionMethod($projector, 'sourceMapForRoom');
$pinned_dungeon = $database->select('dungeoncrawler_content_dungeon_versions', 'v')
  ->fields('v', ['dungeon_payload'])
  ->condition('version_id', $manifest['version_id'])
  ->condition('dungeon_id', $manifest['dungeon_id'])
  ->execute()->fetchField();
$dungeon = json_decode((string) $pinned_dungeon, TRUE, 512, JSON_THROW_ON_ERROR);
$placements = $dungeon['room_placements'] ?? [];
if (count($placements) !== 10 || ($dungeon['metadata']['module_conversion']['quest_template_id'] ?? NULL) !== 'island-survey-v1') {
  throw new \RuntimeException('The immutable survey dungeon must contain ten quest-linked room placements.');
}
$roles = [];
$images = [];
$asset_urls = [];
foreach ($placements as $placement) {
  $room_json = $database->select('dungeoncrawler_content_room_versions', 'v')
    ->fields('v', ['room_payload'])
    ->condition('version_id', $placement['version_id'])
    ->condition('room_id', $placement['room_id'])
    ->execute()->fetchField();
  $room = json_decode((string) $room_json, TRUE, 512, JSON_THROW_ON_ERROR);
  $scene = $room['metadata']['module_conversion']['source_scene'] ?? [];
  $role = $scene['map_review']['role'] ?? '';
  $roles[$role] = ($roles[$role] ?? 0) + 1;
  $art = $projection->invoke($projector, $room);
  if (($scene['conversion_state'] ?? NULL) !== 'playable_validated'
    || ($scene['map_review']['terrain_basis'] ?? NULL) !== 'original_canonical'
    || ($art['alignment'] ?? NULL) !== NULL
    || ($art['background_url'] ?? NULL) !== ($scene['map_assets']['background']['url'] ?? NULL)
    || count((array) ($room['hexes'] ?? [])) < 16
    || count((array) ($room['placements'] ?? [])) < 1) {
    throw new \RuntimeException('A survey room lacks its reviewed adaptation, canonical terrain, or illustrative map.');
  }
  foreach ([$scene['map_assets']['background'], ...$scene['map_assets']['tiles']] as $asset) {
    $images[(int) $asset['image_id']] = TRUE;
    $asset_urls[$asset['url']] = TRUE;
    $asset_urls[$asset['license_notice_url']] = TRUE;
  }
}
if (($roles['interpretive_gallery'] ?? 0) !== 9 || ($roles['overland_overview'] ?? 0) !== 1 || count($images) !== 19) {
  throw new \RuntimeException('The source map review must preserve nine illustrative scenes and one overland overview with all 19 assets.');
}
foreach ($releases->listPublishedCampaignSources() as $source) {
  if ($source['release_id'] === $release_id && $expected_status !== 'published') {
    throw new \RuntimeException('The unpublished survey revision was exposed before approval.');
  }
}
if ($expected_status === 'published'
  && !in_array($release_id, array_column($releases->listPublishedCampaignSources(), 'release_id'), TRUE)) {
  throw new \RuntimeException('The published survey revision is missing from campaign sources.');
}
$old_release_id = $database->select('dungeoncrawler_content_module_releases', 'r')
  ->fields('r', ['release_id'])
  ->condition('module_id', 'the-island-adventure')
  ->condition('module_version', '1.1.0')
  ->condition('conversion_revision', 'island-staged-v1')
  ->range(0, 1)->execute()->fetchField();
if (!$old_release_id || in_array($old_release_id, array_column($releases->listPublishedCampaignSources(), 'release_id'), TRUE)) {
  throw new \RuntimeException('The older staged Island release must remain unavailable.');
}

$transaction = $database->startTransaction();
try {
  $publication_status = $expected_status === 'published'
    ? $expected_status
    : $releases->registerPublishedManifest($release_id, $manifest, 1)['publication_status'];
  $coverage = $releases->sceneCoverageReport($release_id);
  if ($publication_status !== 'published'
    || ($coverage['complete'] ?? FALSE) !== TRUE
    || ($coverage['quest_verified'] ?? FALSE) !== TRUE
    || ($coverage['placed_count'] ?? NULL) !== 10
    || ($coverage['blocking_findings'] ?? NULL) !== []) {
    throw new \RuntimeException('The survey release did not pass the positive publication and quest coverage gates.');
  }
  $quest_schema = (string) $database->select('dc_canonical_quests', 'q')
    ->fields('q', ['objectives_schema'])
    ->condition('template_id', 'island-survey-v1')->execute()->fetchField();
  $tampered_schema = json_decode($quest_schema, TRUE, 512, JSON_THROW_ON_ERROR);
  $tampered_schema[0]['objectives'][0]['location_id'] = 'unplaced-room';
  $database->update('dc_canonical_quests')->fields([
    'objectives_schema' => json_encode($tampered_schema, JSON_THROW_ON_ERROR),
  ])->condition('template_id', 'island-survey-v1')->execute();
  $invalid_quest = $releases->sceneCoverageReport($release_id);
  if (($invalid_quest['complete'] ?? TRUE) !== FALSE
    || ($invalid_quest['quest_verified'] ?? TRUE) !== FALSE
    || ($invalid_quest['blocking_findings'][0]['code'] ?? '') !== 'module_release_reviewed_quest_incomplete') {
    throw new \RuntimeException('The import validator did not identify a quest mismatch against the pinned rooms.');
  }
  if (in_array($release_id, array_column($releases->listPublishedCampaignSources(), 'release_id'), TRUE)) {
    throw new \RuntimeException('A stale reviewed quest remained selectable as a launchable module.');
  }
  $database->update('dc_canonical_quests')->fields([
    'objectives_schema' => $quest_schema,
  ])->condition('template_id', 'island-survey-v1')->execute();
  $campaign_id = \Drupal::service('dungeoncrawler_content.campaign_initialization')->initializeCampaign(
    1, 'Island survey launch audit', 'island_exploration', 'normal',
    ['kind' => 'module_release', 'release_id' => $release_id]
  );
  $quest = $database->select('dc_campaign_quests', 'q')
    ->fields('q', ['quest_id', 'status', 'generated_objectives'])
    ->condition('campaign_id', $campaign_id)
    ->condition('source_template_id', 'island-survey-v1')
    ->range(0, 1)->execute()->fetchAssoc();
  $objectives = is_array($quest) ? json_decode((string) $quest['generated_objectives'], TRUE, 512, JSON_THROW_ON_ERROR) : [];
  if ($campaign_id <= 0 || ($quest['status'] ?? NULL) !== 'offered'
    || count((array) ($objectives[0]['objectives'] ?? [])) !== 10
    || (int) $database->select('dc_campaign_rooms', 'r')->condition('campaign_id', $campaign_id)->countQuery()->execute()->fetchField() !== 10) {
    throw new \RuntimeException('Survey launch must materialize ten rooms and an offered ten-scene quest.');
  }
  $source_links = (int) $database->select('dungeoncrawler_content_connections', 'c')
    ->condition('dungeon_id', $manifest['dungeon_id'])->countQuery()->execute()->fetchField();
  $runtime_links = (int) $database->select('dc_campaign_connections', 'c')
    ->condition('campaign_id', $campaign_id)->countQuery()->execute()->fetchField();
  if ($source_links < 9 || $runtime_links !== $source_links) {
    throw new \RuntimeException('The campaign room graph must preserve the canonical published connectors.');
  }
  if (!\Drupal::service('dungeoncrawler_content.storyline_quest_lifecycle')
    ->startOfferedQuest($campaign_id, (string) $quest['quest_id'])) {
    throw new \RuntimeException('The offered survey quest did not activate.');
  }
  $progress = \Drupal::service('dungeoncrawler_content.quest_tracker')->updateObjectiveProgress(
    $campaign_id, (string) $quest['quest_id'], (string) $objectives[0]['objectives'][0]['objective_id'], 1
  );
  if (($progress['success'] ?? FALSE) !== TRUE) {
    throw new \RuntimeException('The first exploration objective did not advance in the canonical quest lifecycle.');
  }
}
finally {
  $transaction->rollBack();
}
if ($database->select('dungeoncrawler_content_module_releases', 'r')
  ->fields('r', ['publication_status'])->condition('release_id', $release_id)->execute()->fetchField() !== $expected_status) {
  throw new \RuntimeException('Rollback trial changed the Island survey publication status.');
}
if ((int) $database->select('dc_campaign_rooms', 'r')->condition('campaign_id', $campaign_id)->countQuery()->execute()->fetchField() !== 0
  || (int) $database->select('dc_campaign_quests', 'q')->condition('campaign_id', $campaign_id)->countQuery()->execute()->fetchField() !== 0) {
  throw new \RuntimeException('Rollback trial left campaign rooms or quests behind.');
}
echo "Reviewed Island survey: ten projected rooms, 19 illustrative assets, ten quest objectives, connector parity and quest progress passed; release remains {$expected_status}.\n";
