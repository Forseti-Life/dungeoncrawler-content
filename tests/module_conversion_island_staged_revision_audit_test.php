<?php

/**
 * Read-only verification of a persisted, explicitly non-playable Island draft.
 *
 * Run with the site's Drush and a 2 GB PHP limit.
 */

use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionReadException;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeGenerationException;

$database = \Drupal::database();
$row = $database->select('dungeoncrawler_content_module_releases', 'r')
  ->fields('r', ['release_id', 'publication_status', 'mapping_data'])
  ->condition('module_id', 'the-island-adventure')
  ->condition('module_version', '1.1.0')
  ->condition('conversion_revision', 'island-staged-v1')
  ->range(0, 1)->execute()->fetchAssoc();
if (!is_array($row) || $row['publication_status'] !== 'receipts_recorded') {
  throw new \RuntimeException('Island staging revision must exist and remain unpublished.');
}
$mapping = json_decode($row['mapping_data'], TRUE, 512, JSON_THROW_ON_ERROR);
$receipts = $mapping['native_receipts'];
if (($mapping['native_receipt_summary']['recorded_count'] ?? NULL) !== 15532
  || count($receipts) !== 15532) {
  throw new \RuntimeException('Island conversion must retain all 15,532 native receipts.');
}
$dungeon_receipt = NULL;
$scenes = [];
foreach ($receipts as $resource_id => $receipt) {
  if ($receipt['owner'] === 'DungeonEditorService') {
    if ($dungeon_receipt !== NULL) {
      throw new \RuntimeException('Island conversion has more than one dungeon.');
    }
    $dungeon_receipt = $receipt;
  }
  elseif ($receipt['owner'] === 'RoomEditorService') {
    $scenes[$resource_id] = $receipt;
  }
}
if (!is_array($dungeon_receipt) || count($scenes) !== 10) {
  throw new \RuntimeException('Island conversion must pin exactly ten rooms and one dungeon.');
}
$dungeon_json = $database->select('dungeoncrawler_content_dungeon_versions', 'v')
  ->fields('v', ['dungeon_payload'])
  ->condition('v.dungeon_id', $dungeon_receipt['target_id'])
  ->condition('v.version_id', $dungeon_receipt['target_version'])
  ->execute()->fetchField();
$dungeon = is_string($dungeon_json) ? json_decode($dungeon_json, TRUE, 512, JSON_THROW_ON_ERROR) : NULL;
$pins = [];
foreach ((array) ($dungeon['room_placements'] ?? []) as $placement) {
  $key = $placement['room_id'] . ':' . $placement['version_id'];
  $pins[$key] = ($pins[$key] ?? 0) + 1;
}
if (count($pins) !== 10
  || ($dungeon['metadata']['module_conversion']['map_attachment_state'] ?? NULL) !== 'staged') {
  throw new \RuntimeException('Pinned Island dungeon must contain ten staged-map placements.');
}
$image_ids = [];
$tile_count = 0;
$projector = \Drupal::service('dungeoncrawler_content.map_visual_state_projector');
$source_map_projection = new \ReflectionMethod($projector, 'sourceMapForRoom');
foreach ($scenes as $resource_id => $receipt) {
  $key = $receipt['target_id'] . ':' . $receipt['target_version'];
  if (($pins[$key] ?? 0) !== 1) {
    throw new \RuntimeException('A scene receipt is missing its exact dungeon placement.');
  }
  $room_json = $database->select('dungeoncrawler_content_room_versions', 'v')
    ->fields('v', ['room_payload'])
    ->condition('v.room_id', $receipt['target_id'])
    ->condition('v.version_id', $receipt['target_version'])
    ->execute()->fetchField();
  $room = is_string($room_json) ? json_decode($room_json, TRUE, 512, JSON_THROW_ON_ERROR) : NULL;
  $scene = $room['metadata']['module_conversion']['source_scene'] ?? [];
  if (($room['metadata']['module_conversion']['resource_id'] ?? NULL) !== $resource_id
    || ($scene['conversion_state'] ?? NULL) !== 'original_gameplay_generated_map_staged'
    || isset($scene['map_alignment'])
    || !is_array($scene['map_assets']['background'] ?? NULL)
    || !is_array($scene['map_assets']['tiles'] ?? NULL)) {
    throw new \RuntimeException('Staged scene metadata and unreviewed alignment do not match the pinned room.');
  }
  $images = [$scene['map_assets']['background'], ...$scene['map_assets']['tiles']];
  $tile_count += count($scene['map_assets']['tiles']);
  $descriptor = $source_map_projection->invoke($projector, $room);
  if (($descriptor['background_url'] ?? NULL) !== $scene['map_assets']['background']['url']
    || ($descriptor['background_license_notice_url'] ?? NULL) !== $scene['map_assets']['background']['license_notice_url']
    || count((array) ($descriptor['tiles'] ?? [])) !== count($scene['map_assets']['tiles'])
    || ($descriptor['alignment'] ?? NULL) !== NULL) {
    throw new \RuntimeException('Staged source art must project to gameplay as a gallery without inventing alignment.');
  }
  foreach ($images as $image) {
    $image_id = (int) ($image['image_id'] ?? 0);
    $stored = $database->select('dc_generated_images', 'i')
      ->fields('i', ['file_uri', 'sha256', 'provider', 'provider_request_id'])
      ->condition('id', $image_id)
      ->range(0, 1)->execute()->fetchAssoc();
    if (!is_array($stored) || $stored['provider'] !== 'module-import'
      || $stored['provider_request_id'] !== hash_file('sha256', '/root/.copilot/session-state/6d0c0ae4-1b32-4532-a93a-56ed72d05776/files/module-downloads/the-island-adventure-1.1.0.zip')
      || $stored['sha256'] !== $image['sha256']
      || !is_file(\Drupal::service('file_system')->realpath($stored['file_uri']))
      || !is_file(\Drupal::service('file_system')->realpath($stored['file_uri'] . '.LICENSE.txt'))
      || ($image['license_notice_url'] ?? '') === '') {
      throw new \RuntimeException('Pinned room refers to an absent or unauthenticated staged image.');
    }
    $image_ids[$image_id] = TRUE;
  }
}
if ($tile_count !== 9 || count($image_ids) !== 19) {
  throw new \RuntimeException('Island pin must retain ten backgrounds and nine distinct tile occurrences.');
}
$releases = \Drupal::service('dungeoncrawler_content.module_conversion.release');
if (in_array($row['release_id'], array_column($releases->listPublishedCampaignSources(), 'release_id'), TRUE)) {
  throw new \RuntimeException('Unreviewed Island revision is visible in module campaign sources.');
}
try {
  $releases->resolveCampaignSource($row['release_id']);
  throw new \RuntimeException('Unreviewed Island revision unexpectedly resolved for module campaign launch.');
}
catch (ModuleConversionReadException $e) {
  if ($e->getMessage() !== 'module_release_not_published') {
    throw $e;
  }
}
try {
  \Drupal::service('dungeoncrawler_content.runtime_canonical_content_resolver')->resolvePublishedDungeonSource([
    'dungeon_id' => $dungeon_receipt['target_id'],
    'version_id' => $dungeon_receipt['target_version'],
  ]);
  throw new \RuntimeException('Staged Island dungeon unexpectedly resolved as a generic published dungeon.');
}
catch (RuntimeGenerationException $e) {
  if ($e->getMessage() !== 'campaign_source_module_release_required') {
    throw $e;
  }
}
$transaction = $database->startTransaction();
try {
  try {
    $releases->registerPublishedManifest($row['release_id'], [
      'dungeon_id' => $dungeon_receipt['target_id'],
      'version_id' => $dungeon_receipt['target_version'],
      'receipt_resource_id' => $dungeon_receipt['resource_id'],
    ], 1);
    throw new \RuntimeException('Unreviewed Island revision unexpectedly passed publication.');
  }
  catch (ModuleConversionReadException $e) {
    if ($e->getMessage() !== 'module_release_scene_coverage_incomplete') {
      throw $e;
    }
  }
}
finally {
  $transaction->rollBack();
}
echo "Durable Island conversion: 10 pinned staged rooms, 19 attested assets, 15532 receipts; publication correctly denied.\n";
