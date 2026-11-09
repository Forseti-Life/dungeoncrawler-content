<?php

/**
 * Stage the original Island story with the reviewed module backgrounds.
 *
 * Uses the same canonical item definitions and quest text as the published
 * story, with fresh room/dungeon pins and a new quest-template identity.
 */

$island_revision = 'island-story-source-art-v1';
$island_seed = 23;
$database = \Drupal::database();
$releases = [];
foreach (['island-original-story-v1', 'island-survey-v1'] as $revision) {
  $row = $database->select('dungeoncrawler_content_module_releases', 'r')
    ->fields('r', ['mapping_data', 'publication_status'])
    ->condition('module_id', 'the-island-adventure')
    ->condition('module_version', '1.1.0')
    ->condition('conversion_revision', $revision)
    ->execute()->fetchAssoc();
  if (!is_array($row) || $row['publication_status'] !== 'published') {
    throw new \RuntimeException('Published Island story and reviewed survey are required.');
  }
  $releases[$revision] = json_decode($row['mapping_data'], TRUE, 512, JSON_THROW_ON_ERROR);
}
$story = $releases['island-original-story-v1'];
$survey = $releases['island-survey-v1'];
$room_receipts = static function (array $mapping): array {
  $rooms = [];
  foreach ($mapping['native_receipts'] ?? [] as $receipt) {
    if (($receipt['owner'] ?? NULL) === 'RoomEditorService') {
      if (isset($rooms[$receipt['resource_id']])) {
        throw new \RuntimeException('Duplicate Island scene receipt.');
      }
      $rooms[$receipt['resource_id']] = $receipt;
    }
  }
  if (count($rooms) !== 10) {
    throw new \RuntimeException('Island revision must pin ten distinct scenes.');
  }
  return $rooms;
};
$story_rooms = $room_receipts($story);
$survey_rooms = $room_receipts($survey);
if (array_keys($story_rooms) !== array_keys($survey_rooms)) {
  throw new \RuntimeException('Story and survey scene identities differ.');
}
$dungeon_receipts = array_values(array_filter($story['native_receipts'], static fn(array $receipt): bool =>
  ($receipt['owner'] ?? NULL) === 'DungeonEditorService'));
if (count($dungeon_receipts) !== 1
  || ($dungeon_receipts[0]['source_receipt']['quest_template_id'] ?? NULL) !== 'island-original-story-v1') {
  throw new \RuntimeException('Story dungeon has no unique canonical quest receipt.');
}
$dungeon_json = $database->select('dungeoncrawler_content_dungeon_versions', 'v')
  ->fields('v', ['dungeon_payload'])
  ->condition('dungeon_id', $dungeon_receipts[0]['target_id'])
  ->condition('version_id', $dungeon_receipts[0]['target_version'])
  ->execute()->fetchField();
if (!is_string($dungeon_json)) {
  throw new \RuntimeException('Pinned story dungeon version is missing.');
}
$placements = json_decode($dungeon_json, TRUE, 512, JSON_THROW_ON_ERROR)['room_placements'] ?? [];
$resource_by_room = [];
foreach ($story_rooms as $resource_id => $receipt) {
  $resource_by_room[$receipt['target_id']] = $resource_id;
}
$resource_by_location = [];
foreach ($placements as $placement) {
  $resource_id = $resource_by_room[$placement['room_id'] ?? ''] ?? NULL;
  if (!is_string($resource_id) || ($story_rooms[$resource_id]['target_version'] ?? NULL) !== ($placement['version_id'] ?? NULL)
    || !is_string($placement['placement_id'] ?? NULL) || isset($resource_by_location[$placement['placement_id']])) {
    throw new \RuntimeException('Story dungeon placement differs from its scene receipt.');
  }
  $resource_by_location[$placement['placement_id']] = $resource_id;
}
if (count($resource_by_location) !== 10) {
  throw new \RuntimeException('Story dungeon must place every pinned scene once.');
}
$quest = $database->select('dc_canonical_quests', 'q')
  ->fields('q', ['name', 'description', 'objectives_schema'])
  ->condition('template_id', 'island-original-story-v1')
  ->condition('version', '1.0.0')
  ->execute()->fetchAssoc();
if (!is_array($quest)) {
  throw new \RuntimeException('Pinned original-story quest template is missing.');
}
$phases = json_decode($quest['objectives_schema'], TRUE, 512, JSON_THROW_ON_ERROR);
if (count($phases) !== 1 || count($phases[0]['objectives'] ?? []) !== 20) {
  throw new \RuntimeException('Original-story quest must have ten explore and ten interact objectives.');
}
$objectives = [];
foreach ($phases[0]['objectives'] as $objective) {
  $resource_id = $resource_by_location[$objective['location_id'] ?? ''] ?? NULL;
  if (!is_string($resource_id) || !in_array($objective['type'] ?? NULL, ['explore', 'interact'], TRUE)
    || isset($objectives[$resource_id][$objective['type']])) {
    throw new \RuntimeException('Original-story objective cannot be mapped to its scene.');
  }
  $objectives[$resource_id][$objective['type']] = $objective;
}

$island_reviewed_scenes = [];
$island_authored_scenes = [];
foreach ($story_rooms as $resource_id => $receipt) {
  $pinned_room = static function (array $pin) use ($database): array {
    $json = $database->select('dungeoncrawler_content_room_versions', 'v')
      ->fields('v', ['room_payload'])
      ->condition('room_id', $pin['target_id'])
      ->condition('version_id', $pin['target_version'])
      ->execute()->fetchField();
    if (!is_string($json)) {
      throw new \RuntimeException('Pinned Island room version is missing.');
    }
    return json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
  };
  $room = $pinned_room($receipt);
  $survey_room = $pinned_room($survey_rooms[$resource_id]);
  $source = $survey_room['metadata']['module_conversion']['source_scene'] ?? NULL;
  $story_source = $room['metadata']['module_conversion']['source_scene'] ?? NULL;
  $review = $source['map_review'] ?? NULL;
  $asset = $source['map_assets']['background'] ?? NULL;
  if (!is_array($review) || !is_array($asset)
    || ($asset['archive_entry'] ?? NULL) !== ($story_source['background_archive_entry'] ?? NULL)
    || !is_string($asset['sha256'] ?? NULL) || !is_string($asset['license_notice_url'] ?? NULL)
    || count($room['placements'] ?? []) !== 1
    || ($room['placements'][0]['definition_ref']['family'] ?? NULL) !== 'item') {
    throw new \RuntimeException('Story scene lacks a matching reviewed source image or unique item.');
  }
  $explore = $objectives[$resource_id]['explore'] ?? NULL;
  $interact = $objectives[$resource_id]['interact'] ?? NULL;
  $item = $room['placements'][0]['definition_ref'];
  if (!is_array($explore) || !is_array($interact)
    || ($interact['item'] ?? NULL) !== $item['definition_id']
    || ($interact['next_step'] ?? NULL) !== ($explore['next_step'] ?? NULL)) {
    throw new \RuntimeException('Story item and quest objectives differ at a pinned scene.');
  }
  $asset_name = basename($asset['archive_entry']);
  if (isset($island_reviewed_scenes[$asset_name])) {
    throw new \RuntimeException('Two scenes claim the same reviewed source background.');
  }
  $island_reviewed_scenes[$asset_name] = ['map_review' => $review];
  $island_authored_scenes[$resource_id] = [
    'definition' => [
      'metadata' => [
        'name' => $room['name'],
        'description' => $room['description'],
        'theme' => 'island_exploration',
        'rights_status' => 'project_original',
      ],
      'definitions' => [[
        'family' => 'item',
        'definition_id' => $item['definition_id'],
        'version' => $item['version'],
        'rights_status' => 'project_original',
      ]],
      'map_review' => $review,
    ],
    'objective' => [
      'description' => $explore['description'],
      'next_step' => $explore['next_step'],
      'interaction' => [
        'description' => $interact['description'],
        'item_definition_id' => $item['definition_id'],
      ],
    ],
  ];
}
$island_reviewed_quest = [
  'template_id' => 'island-story-source-art-v1',
  'name' => $quest['name'],
  'description' => $quest['description'],
  'rights_status' => 'project_original',
];
require __DIR__ . '/island_map_conversion_stage.php';
