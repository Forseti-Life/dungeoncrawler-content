<?php

/**
 * Stage an original Island revision with reviewed source art or original art.
 *
 * Run once with the site-local Drush and a 2 GB PHP limit. This creates a
 * receipts_recorded revision, NOT a published or playable module release.
 */

require_once __DIR__ . '/island_map_rights.php';

$archive = '/root/.copilot/session-state/6d0c0ae4-1b32-4532-a93a-56ed72d05776/files/module-downloads/the-island-adventure-1.1.0.zip';
$revision = $island_revision ?? 'island-staged-v1';
$reviewed_scenes = $island_reviewed_scenes ?? [];
$authored_scenes = $island_authored_scenes ?? [];
$reviewed_quest = $island_reviewed_quest ?? NULL;
$notice = islandMapNotice();
$database = \Drupal::database();
$inspector = \Drupal::service('dungeoncrawler_content.module_conversion.archive_inspector');
$releases = \Drupal::service('dungeoncrawler_content.module_conversion.release');
$definitions = \Drupal::service('dungeoncrawler_content.canonical_definitions');
$inventory = $inspector->inspectArchive($archive);
$plan = \Drupal::service('dungeoncrawler_content.module_conversion.plan')->buildPlan($inventory);
$existing = $database->select('dungeoncrawler_content_module_releases', 'r')
  ->fields('r', ['release_id', 'publication_status'])
  ->condition('module_id', 'the-island-adventure')
  ->condition('module_version', '1.1.0')
  ->condition('conversion_revision', $revision)
  ->range(0, 1)->execute()->fetchAssoc();
if (is_array($existing) && $existing['publication_status'] !== 'requires_authoring') {
  throw new \RuntimeException('Island staging revision is already complete or published; do not regenerate immutable pins.');
}
$base = $database->select('dungeoncrawler_content_module_releases', 'r')
  ->fields('r', ['release_id'])
  ->condition('module_id', 'the-island-adventure')
  ->condition('module_version', '1.1.0')
  ->condition('conversion_revision', 'base')
  ->range(0, 1)->execute()->fetchField();
if (!is_string($base) || $base === '') {
  throw new \RuntimeException('Published Island base release is needed to reuse verified unchanged receipts.');
}
$scenes = [];
$adventures = [];
foreach ($plan['resources'] as $resource) {
  if (($resource['resource_type'] ?? NULL) === 'scene') {
    $scenes[$resource['resource_id']] = $resource;
  }
  elseif (($resource['resource_type'] ?? NULL) === 'adventure') {
    $adventures[] = $resource;
  }
}
if (count($scenes) !== 10 || count($adventures) !== 1) {
  throw new \RuntimeException('Island plan must contain exactly ten scenes and one adventure.');
}
$item_id = 'island-original-wayfinding-v1';
if (!$definitions->definitionExists('item', $item_id, '1.0.0')) {
  $definitions->saveDefinition('item', NULL, [
    'schema_version' => '1.0.0',
    'item_id' => $item_id,
    'name' => 'Original Wayfinding Marker',
    'item_type' => 'adventuring_gear',
    'level' => 0,
    'rarity' => 'common',
    'description' => 'An original marker for navigating the Island conversion draft. It does not implement story objectives.',
  ]);
}
$scene_definitions = [];
$reviewed_objectives = [];
foreach ($scenes as $resource_id => $scene) {
  if ($authored_scenes !== []) {
    $authored = $authored_scenes[$resource_id] ?? NULL;
    if (!is_array($authored) || !is_array($authored['definition'] ?? NULL)
      || !is_array($authored['objective'] ?? NULL)) {
      throw new \RuntimeException('Every Island scene must have an exact authored definition and objective.');
    }
    if ($reviewed_scenes !== []) {
      $context = $scene['source']['source_context'];
      $asset_name = basename((string) $context['background_archive_entry']);
      if (($authored['definition']['map_review'] ?? NULL) !== ($reviewed_scenes[$asset_name]['map_review'] ?? NULL)
        || isset($authored['definition']['maps']) || isset($authored['definition']['original_map'])) {
        throw new \RuntimeException('Authored Island scene lacks its exact reviewed module background.');
      }
      $rights_for = static fn(string $entry): array => islandMapRights($inventory, $resource_id, $entry, $notice);
      $authored['definition']['maps'] = [
        'background' => $rights_for((string) $context['background_archive_entry']),
        'tiles' => array_map($rights_for, (array) ($context['tile_archive_entries'] ?? [])),
      ];
    }
    $scene_definitions[$resource_id] = $authored['definition'];
    $reviewed_objectives[$resource_id] = $authored['objective'];
    continue;
  }
  $context = $scene['source']['source_context'];
  $asset_name = basename((string) $context['background_archive_entry']);
  $review = $reviewed_scenes[$asset_name] ?? NULL;
  if ($reviewed_scenes !== [] && !is_array($review)) {
    throw new \RuntimeException('Every Island background must have an explicit reviewed adaptation.');
  }
  if ($authored_scenes !== [] && count($authored_scenes) !== count($scenes)) {
    throw new \RuntimeException('Authored Island scenes must match the exact plan scene set.');
  }
  $rights_for = static fn(string $entry): array => islandMapRights($inventory, $resource_id, $entry, $notice);
  $scene_definitions[$resource_id] = [
    'metadata' => [
      'name' => $review['name'] ?? 'Original Island room ' . (count($scene_definitions) + 1),
      'description' => $review['description'] ?? 'An original generated exploration room; art and source mechanics still require gameplay review.',
      'theme' => 'island_exploration',
      'rights_status' => 'project_original',
    ],
    'definitions' => [[
      'family' => 'item',
      'definition_id' => $item_id,
      'version' => '1.0.0',
      'rights_status' => 'project_original',
    ]],
    'maps' => [
      'background' => $rights_for((string) $context['background_archive_entry']),
      'tiles' => array_map($rights_for, (array) ($context['tile_archive_entries'] ?? [])),
    ],
    ...($review === NULL ? [] : ['map_review' => $review['map_review']]),
  ];
  if ($review !== NULL) {
    $reviewed_objectives[$resource_id] = $review['objective'];
  }
}
if ($reviewed_scenes !== [] && count($reviewed_objectives) !== count($reviewed_scenes)) {
  throw new \RuntimeException('Island review must cover exactly the ten pinned source backgrounds.');
}
$release = $releases->registerRelease($inventory, $plan, NULL, 1, ['conversion_revision' => $revision]);
$release_id = (string) $release['release_id'];
unset($inventory, $plan, $release);
$reuse = $releases->reuseVerifiedExecution($release_id, $base, 1);
if ($reuse['reused_count'] !== 15513 || count($reuse['changed_resource_ids']) !== 8) {
  throw new \RuntimeException('Unexpected reusable Island execution count; do not proceed with scene publication.');
}
$pages = $releases->executePlannedCommands($release_id, [
  'publish_version' => '1.0.0',
  'resource_ids' => $reuse['changed_resource_ids'],
], 1);
if (($pages['run']['succeeded'] ?? NULL) !== 8 || ($pages['run']['failed'] ?? NULL) !== 0) {
  throw new \RuntimeException('Changed Island journal pages did not execute.');
}
unset($pages);
$result = $releases->executePlannedCommands($release_id, [
  'publish_version' => '1.0.0',
  'register_receipts' => TRUE,
  'original_scene_generation' => [
    'scene_definitions' => $scene_definitions,
    'terminal_scene_resource_id' => array_key_last($scene_definitions),
    'dungeon' => [
      'name' => $authored_scenes !== [] ? 'The Island: Original Waymarks' : ($reviewed_scenes !== [] ? 'The Island: Original Survey' : 'Original Island Exploration Draft'),
      'description' => $authored_scenes !== []
        ? ($reviewed_scenes !== []
          ? 'Ten original Forseti locations and waymarks with licensed module illustrations; authored hexes are not source-map traces.'
          : 'Ten original Forseti locations and their distinct waymarks. Illustrations and routes are original adaptations, not source-map traces.')
        : ($reviewed_scenes !== []
        ? 'Explore ten original Forseti locations. The licensed source maps are illustrations, not tactical terrain traces.'
        : 'Connected original rooms and staged source art; gameplay content and map terrain are awaiting review.'),
      'theme' => 'island_exploration',
      'rights_status' => 'project_original',
    ],
    ...($reviewed_quest === NULL ? [] : ['quest' => $reviewed_quest + ['objectives' => $reviewed_objectives]]),
    'seed' => $island_seed ?? 7,
  ],
], 1);
if (($result['run']['succeeded'] ?? NULL) !== 11
  || ($result['run']['failed'] ?? NULL) !== 0
  || ($result['run']['receipts_registered'] ?? NULL) !== TRUE
  || ($result['release']['publication_status'] ?? NULL) !== 'receipts_recorded'
  || ($result['release']['mapping']['native_receipt_summary']['recorded_count'] ?? NULL) !== 15532) {
  throw new \RuntimeException('Island staging did not complete all native receipts without publishing.');
}
$details = array_column($result['run']['details'], NULL, 'resource_id');
$dungeon = $details[$adventures[0]['resource_id']] ?? NULL;
if (!is_array($dungeon) || ($dungeon['owner'] ?? NULL) !== 'DungeonEditorService') {
  throw new \RuntimeException('Island draft has no canonical dungeon pin.');
}
echo 'Island draft revision ' . $revision . ' release_id=' . $release_id
  . ' dungeon=' . $dungeon['target_id'] . '@' . $dungeon['target_version']
  . " receipts=15532/15532; not published or launchable.\n";
