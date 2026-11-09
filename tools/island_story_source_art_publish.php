<?php

/**
 * Publish the pinned Island story/source-art revision after reviewing every room.
 *
 * Run with the site-local Drush. Publication itself enforces the canonical
 * receipt, reviewed-quest and scene-coverage gates.
 */

$database = \Drupal::database();
$row = $database->select('dungeoncrawler_content_module_releases', 'r')
  ->fields('r', ['release_id', 'mapping_data', 'publication_status'])
  ->condition('module_id', 'the-island-adventure')
  ->condition('module_version', '1.1.0')
  ->condition('conversion_revision', 'island-story-source-art-v1')
  ->execute()->fetchAssoc();
if (!is_array($row) || $row['publication_status'] !== 'receipts_recorded') {
  throw new \RuntimeException('Island story/source-art release must have unpublished, complete receipts.');
}
$mapping = json_decode($row['mapping_data'], TRUE, 512, JSON_THROW_ON_ERROR);
if (($mapping['native_receipt_summary']['complete'] ?? FALSE) !== TRUE
  || ($mapping['native_receipt_summary']['recorded_count'] ?? NULL) !== 15532) {
  throw new \RuntimeException('Island story/source-art native receipts are incomplete.');
}
$rooms = [];
$dungeon = [];
foreach ($mapping['native_receipts'] as $receipt) {
  if (($receipt['owner'] ?? NULL) === 'RoomEditorService') {
    $rooms[] = $receipt;
  }
  elseif (($receipt['owner'] ?? NULL) === 'DungeonEditorService') {
    $dungeon[] = $receipt;
  }
}
if (count($rooms) !== 10 || count($dungeon) !== 1
  || ($dungeon[0]['source_receipt']['quest_template_id'] ?? NULL) !== 'island-story-source-art-v1') {
  throw new \RuntimeException('Island story/source-art dungeon or scene pins are invalid.');
}
foreach ($rooms as $receipt) {
  $json = $database->select('dungeoncrawler_content_room_versions', 'v')
    ->fields('v', ['room_payload'])
    ->condition('room_id', $receipt['target_id'])
    ->condition('version_id', $receipt['target_version'])
    ->execute()->fetchField();
  if (!is_string($json)) {
    throw new \RuntimeException('Pinned Island source-art room is missing.');
  }
  $room = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
  $scene = $room['metadata']['module_conversion']['source_scene'] ?? NULL;
  $maps = $scene['map_assets'] ?? NULL;
  $background = $maps['background'] ?? NULL;
  if (!is_array($scene) || !is_array($maps) || !is_array($background)
    || ($scene['conversion_state'] ?? NULL) !== 'playable_validated'
    || ($scene['map_review']['terrain_basis'] ?? NULL) !== 'original_canonical'
    || isset($scene['original_map_review']) || isset($scene['map_alignment'])
    || !is_string($background['sha256'] ?? NULL)
    || !preg_match('/^[a-f0-9]{64}$/D', $background['sha256'])
    || !is_string($background['archive_entry'] ?? NULL)
    || ($background['archive_entry'] ?? NULL) !== ($scene['background_archive_entry'] ?? NULL)
    || !is_string($background['license_notice_url'] ?? NULL)
    || !is_string($background['author'] ?? NULL)
    || !is_array($maps['tiles'] ?? NULL)
    || count($maps['tiles']) !== count($scene['tile_archive_entries'] ?? [])
    || count($room['hexes'] ?? []) < 16
    || count($room['placements'] ?? []) !== 1) {
    throw new \RuntimeException('Island scene lacks reviewed source art, authored hexes or its story item: ' . $receipt['resource_id']);
  }
  foreach ($maps['tiles'] as $index => $tile) {
    if (($tile['archive_entry'] ?? NULL) !== $scene['tile_archive_entries'][$index]
      || !is_string($tile['sha256'] ?? NULL)
      || !is_string($tile['license_notice_url'] ?? NULL)
      || !is_array($tile['placement'] ?? NULL)) {
      throw new \RuntimeException('Pinned Island source-art tile differs from the reviewed scene.');
    }
  }
}
$manifest = [
  'dungeon_id' => $dungeon[0]['target_id'],
  'version_id' => $dungeon[0]['target_version'],
  'receipt_resource_id' => $dungeon[0]['resource_id'],
];
$releases = \Drupal::service('dungeoncrawler_content.module_conversion.release');
$published = $releases->registerPublishedManifest($row['release_id'], $manifest, 1);
$source = $releases->resolveCampaignSource($row['release_id']);
if (($published['publication_status'] ?? NULL) !== 'published'
  || ($source['dungeon_id'] ?? NULL) !== $manifest['dungeon_id']
  || ($source['version_id'] ?? NULL) !== $manifest['version_id']) {
  throw new \RuntimeException('Island source-art release did not publish against its exact pins.');
}
echo 'Published Island story/source-art release ' . $row['release_id']
  . ' dungeon=' . $manifest['dungeon_id'] . '@' . $manifest['version_id'] . PHP_EOL;
