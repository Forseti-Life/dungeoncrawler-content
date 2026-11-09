<?php

/**
 * Isolated ten-scene generation/assembly check; never publishes a module release.
 *
 * Run with the site's Drush:
 *   ./vendor/bin/drush --no-interaction php:script tests/module_conversion_original_batch_validation_test.php
 */

$archive = '/root/.copilot/session-state/6d0c0ae4-1b32-4532-a93a-56ed72d05776/files/module-downloads/the-island-adventure-1.1.0.zip';
require_once __DIR__ . '/../tools/island_map_rights.php';
if (!is_file($archive)) {
  throw new \RuntimeException('Island validation archive is unavailable.');
}

$inspector = \Drupal::service('dungeoncrawler_content.module_conversion.archive_inspector');
$planner = \Drupal::service('dungeoncrawler_content.module_conversion.plan');
$releases = \Drupal::service('dungeoncrawler_content.module_conversion.release');
$definitions = \Drupal::service('dungeoncrawler_content.canonical_definitions');
$inventory = $inspector->inspectArchive($archive);
$inventory_hash = $inventory['archive']['sha256'];
$map_staging = \Drupal::service('dungeoncrawler_content.module_conversion.scene_map_staging');
$database = \Drupal::database();
$existing_image_ids = array_fill_keys(array_map('intval', $database->select('dc_generated_images', 'i')
  ->fields('i', ['id'])
  ->condition('provider', 'module-import')
  ->condition('provider_request_id', $inventory_hash)
  ->execute()->fetchCol()), TRUE);
$notice = islandMapNotice();
$plan = $planner->buildPlan($inventory);
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
  throw new \RuntimeException('The Island validation plan must contain exactly ten scenes and one adventure.');
}

$transaction = \Drupal::database()->startTransaction();
try {
  $item_id = 'original-conversion-validation-' . bin2hex(random_bytes(5));
  $definitions->saveDefinition('item', NULL, [
    'schema_version' => '1.0.0',
    'item_id' => $item_id,
    'name' => 'Original Wayfinding Marker',
    'item_type' => 'adventuring_gear',
    'level' => 0,
    'rarity' => 'common',
    'description' => 'An original marker used solely to validate room placements.',
  ]);
  $scene_definitions = [];
  foreach ($scenes as $resource_id => $scene) {
    $context = $scene['source']['source_context'];
    $rights_for = static fn(string $entry): array => islandMapRights($inventory, $resource_id, $entry, $notice);
    $scene_definitions[$resource_id] = [
      'metadata' => [
        'name' => 'Original room ' . (count($scene_definitions) + 1),
        'description' => 'An original navigable room generated for an isolated conversion check.',
        'theme' => 'wayfinding',
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
      'map_review' => [
        'role' => str_contains((string) $context['background_archive_entry'], '/the-island/')
          ? 'overland_overview' : 'interpretive_gallery',
        'terrain_basis' => 'original_canonical',
        'notes' => str_contains((string) $context['background_archive_entry'], '/the-island/')
          ? 'This is an overland island overview; it illustrates the journey but does not define tactical hex terrain.'
          : 'This source image illustrates the location; the original canonical room topology is an adaptation, not a trace of its pixels.',
      ],
    ];
  }
  $base_release_id = \Drupal::database()->select('dungeoncrawler_content_module_releases', 'r')
    ->fields('r', ['release_id'])
    ->condition('module_id', 'the-island-adventure')
    ->condition('module_version', '1.1.0')
    ->condition('conversion_revision', 'base')
    ->execute()->fetchField();
  if (!is_string($base_release_id) || $base_release_id === '') {
    throw new \RuntimeException('Immutable Island base release is required to validate receipt reuse.');
  }
  $release = $releases->registerRelease($inventory, $plan, NULL, 1, [
    'conversion_revision' => 'rollback-' . bin2hex(random_bytes(5)),
  ]);
  $release_id = (string) $release['release_id'];
  $quest_template_id = 'island-trial-' . str_replace('-', '', $release_id);
  unset($release, $plan, $inventory);
  $reused = $releases->reuseVerifiedExecution($release_id, $base_release_id, 1);
  if ($reused['reused_count'] !== 15513 || count($reused['changed_resource_ids']) !== 8) {
    throw new \RuntimeException('Island receipt reuse must retain only 15,513 identical non-scene resources.');
  }
  $changed = $releases->executePlannedCommands($release_id, [
    'publish_version' => '1.0.0',
    'resource_ids' => $reused['changed_resource_ids'],
  ], 1);
  if (($changed['run']['succeeded'] ?? NULL) !== 8 || ($changed['run']['failed'] ?? NULL) !== 0) {
    throw new \RuntimeException('Changed Island journal pages did not execute through the canonical runner.');
  }
  unset($changed);
  $run = $releases->executePlannedCommands($release_id, [
    'publish_version' => '1.0.0',
    'register_receipts' => TRUE,
    'original_scene_generation' => [
      'scene_definitions' => $scene_definitions,
      'terminal_scene_resource_id' => array_key_last($scene_definitions),
      'dungeon' => [
        'name' => 'Original Island Validation',
        'description' => 'Original linked rooms for a rollback-only validation.',
        'theme' => 'wayfinding',
        'rights_status' => 'project_original',
      ],
      'quest' => [
        'template_id' => $quest_template_id,
        'name' => 'Chart the Island Passage',
        'description' => 'Survey each of the original exploration chambers to complete an island route.',
        'rights_status' => 'project_original',
        'objectives' => array_fill_keys(array_keys($scene_definitions), [
          'description' => 'Discover the next exploration chamber.',
          'next_step' => 'Travel through the passage and explore this location.',
        ]),
      ],
      'seed' => 7,
    ],
  ], 1);
  if (($run['run']['succeeded'] ?? NULL) !== 11 || ($run['run']['failed'] ?? NULL) !== 0
    || ($run['run']['receipts_registered'] ?? FALSE) !== TRUE
    || ($run['release']['mapping']['native_receipt_summary']['recorded_count'] ?? NULL) !== 15532) {
    throw new \RuntimeException('Ten-scene generation must complete and register the full-inventory native receipts.');
  }
  $details = array_column($run['run']['details'], NULL, 'resource_id');
  $map_rows = \Drupal::database()->select('dc_generated_images', 'i')
    ->fields('i', ['id'])
    ->condition('provider', 'module-import')
    ->condition('provider_request_id', $inventory_hash)
    ->execute()->fetchCol();
  if (count(array_diff(array_map('intval', $map_rows), array_keys($existing_image_ids))) !== 19) {
    throw new \RuntimeException('Conversion must stage all ten backgrounds and nine tile occurrences.');
  }
  $adventure = $details[$adventures[0]['resource_id']] ?? NULL;
  $dungeon_json = is_array($adventure)
    ? \Drupal::database()->select('dungeoncrawler_content_dungeon_versions', 'v')
      ->fields('v', ['dungeon_payload'])
      ->condition('v.dungeon_id', (string) ($adventure['target_id'] ?? ''))
      ->condition('v.version_id', (string) ($adventure['target_version'] ?? ''))
      ->execute()->fetchField()
    : FALSE;
  $dungeon = is_string($dungeon_json) ? json_decode($dungeon_json, TRUE) : NULL;
  $pins = [];
  foreach ((array) ($dungeon['room_placements'] ?? []) as $placement) {
    $key = (string) ($placement['room_id'] ?? '') . ':' . (string) ($placement['version_id'] ?? '');
    $pins[$key] = ($pins[$key] ?? 0) + 1;
  }
  if (count((array) ($dungeon['room_placements'] ?? [])) !== 10) {
    throw new \RuntimeException('Generated dungeon does not place exactly ten scene rooms.');
  }
  if (($dungeon['metadata']['module_conversion']['quest_template_id'] ?? NULL) !== $quest_template_id) {
    throw new \RuntimeException('Generated dungeon must pin the reviewed original quest template.');
  }
  $quest_json = $database->select('dc_canonical_quests', 'q')
    ->fields('q', ['objectives_schema'])
    ->condition('template_id', $quest_template_id)
    ->execute()->fetchField();
  $quest_phases = is_string($quest_json) ? json_decode($quest_json, TRUE, 512, JSON_THROW_ON_ERROR) : NULL;
  $destinations = array_column((array) ($quest_phases[0]['objectives'] ?? []), 'location_id');
  $placement_ids = array_column($dungeon['room_placements'], 'placement_id');
  sort($destinations);
  sort($placement_ids);
  if (count($destinations) !== 10 || $destinations !== $placement_ids) {
    throw new \RuntimeException('The original quest must bind every objective to one exact published scene placement.');
  }
  $published_trial = $releases->registerPublishedManifest($release_id, [
    'dungeon_id' => (string) $adventure['target_id'],
    'version_id' => (string) $adventure['target_version'],
    'receipt_resource_id' => (string) $adventures[0]['resource_id'],
  ], 1);
  if (($published_trial['publication_status'] ?? NULL) !== 'published') {
    throw new \RuntimeException('Reviewed rollback trial failed to publish its guarded manifest.');
  }
  $coverage = $releases->sceneCoverageReport($release_id);
  if (($coverage['complete'] ?? FALSE) !== TRUE || ($coverage['quest_verified'] ?? FALSE) !== TRUE
    || ($coverage['placed_count'] ?? NULL) !== 10) {
    throw new \RuntimeException('Reviewed original rooms and their canonical quest must pass the positive release coverage gate.');
  }
  $campaign_id = \Drupal::service('dungeoncrawler_content.campaign_initialization')->initializeCampaign(
    1, 'Original Island rollback trial', 'island_exploration', 'normal',
    ['kind' => 'module_release', 'release_id' => $release_id]
  );
  if ($campaign_id <= 0) {
    throw new \RuntimeException('Reviewed Island release did not initialize a playable campaign.');
  }
  $campaign_quest = $database->select('dc_campaign_quests', 'q')
    ->fields('q', ['quest_id', 'source_template_id', 'generated_objectives', 'status'])
    ->condition('campaign_id', $campaign_id)
    ->condition('source_template_id', $quest_template_id)
    ->range(0, 1)->execute()->fetchAssoc();
  $runtime_objectives = is_array($campaign_quest)
    ? json_decode((string) $campaign_quest['generated_objectives'], TRUE, 512, JSON_THROW_ON_ERROR)
    : NULL;
  if (($campaign_quest['status'] ?? NULL) !== 'offered'
    || count((array) ($runtime_objectives[0]['objectives'] ?? [])) !== 10
    || (int) $database->select('dc_campaign_rooms', 'r')->condition('campaign_id', $campaign_id)->countQuery()->execute()->fetchField() !== 10) {
    throw new \RuntimeException('Island campaign must materialize ten runtime rooms and its ten-objective quest.');
  }
  $lifecycle = \Drupal::service('dungeoncrawler_content.storyline_quest_lifecycle');
  if (!$lifecycle->startOfferedQuest($campaign_id, (string) $campaign_quest['quest_id'])) {
    throw new \RuntimeException('Island exploration quest cannot be started through the canonical lifecycle.');
  }
  $first_objective = (string) $runtime_objectives[0]['objectives'][0]['objective_id'];
  $progress = \Drupal::service('dungeoncrawler_content.quest_tracker')
    ->updateObjectiveProgress($campaign_id, (string) $campaign_quest['quest_id'], $first_objective, 1);
  if (($progress['success'] ?? FALSE) !== TRUE) {
    throw new \RuntimeException('Discovering an Island scene must update canonical quest progress.');
  }
  $projection = \Drupal::service('dungeoncrawler_content.canonical_room_projection');
  $placements_by_room = [];
  $entrance_id = NULL;
  foreach ($dungeon['room_placements'] as $placement) {
    $placement_id = (string) ($placement['placement_id'] ?? '');
    $room_id = (string) ($placement['room_id'] ?? '');
    if ($placement_id === '' || $room_id === '' || isset($placements_by_room[$room_id])) {
      throw new \RuntimeException('Generated dungeon has an invalid or duplicate scene placement.');
    }
    $placements_by_room[$room_id] = $placement;
    if (($placement['is_level_entrance'] ?? FALSE) === TRUE) {
      if ($entrance_id !== NULL) {
        throw new \RuntimeException('Generated dungeon has multiple entrances.');
      }
      $entrance_id = $placement_id;
    }
  }
  if ($entrance_id === NULL) {
    throw new \RuntimeException('Generated dungeon has no entrance.');
  }
  $neighbors = [];
  foreach ((array) ($dungeon['port_links'] ?? []) as $link) {
    $from = (string) ($link['from']['placement_id'] ?? '');
    $to = (string) ($link['to']['placement_id'] ?? '');
    if ($from === '' || $to === '') {
      throw new \RuntimeException('Generated dungeon has a malformed port link.');
    }
    $neighbors[$from][] = $to;
    $neighbors[$to][] = $from;
  }
  $reached = [$entrance_id => TRUE];
  $queue = [$entrance_id];
  for ($cursor = 0; $cursor < count($queue); $cursor++) {
    $visited = $queue[$cursor];
    foreach ($neighbors[$visited] ?? [] as $adjacent) {
      if (!isset($reached[$adjacent])) {
        $reached[$adjacent] = TRUE;
        $queue[] = $adjacent;
      }
    }
  }
  if (count($reached) !== 10) {
    throw new \RuntimeException('Generated dungeon does not connect every scene to its entrance.');
  }
  foreach (array_keys($scenes) as $resource_id) {
    $room = $details[$resource_id] ?? NULL;
    $key = (string) ($room['target_id'] ?? '') . ':' . (string) ($room['target_version'] ?? '');
    if (!is_array($room) || $key === ':' || ($pins[$key] ?? 0) !== 1) {
      throw new \RuntimeException('A source-scene room is missing its exact published dungeon pin.');
    }
    $room_id = (string) $room['target_id'];
    $version_id = (string) $room['target_version'];
    $placement = $placements_by_room[$room_id] ?? NULL;
    $room_json = \Drupal::database()->select('dungeoncrawler_content_room_versions', 'v')
      ->fields('v', ['room_payload'])
      ->condition('v.room_id', $room_id)
      ->condition('v.version_id', $version_id)
      ->execute()->fetchField();
    $published_room = is_string($room_json) ? json_decode($room_json, TRUE, 512, JSON_THROW_ON_ERROR) : NULL;
    if (!is_array($placement) || !is_array($published_room)) {
      throw new \RuntimeException('Pinned scene room payload or dungeon placement is missing.');
    }
    $source_map = $published_room['metadata']['module_conversion']['source_scene'] ?? [];
    if (($source_map['conversion_state'] ?? NULL) !== 'playable_validated'
      || ($source_map['map_assets']['background']['license_notice_url'] ?? '') === ''
      || count((array) ($source_map['map_assets']['tiles'] ?? [])) !== count($scenes[$resource_id]['source']['source_context']['tile_archive_entries'] ?? [])
      || isset($source_map['map_alignment'])
      || ($source_map['map_review']['terrain_basis'] ?? NULL) !== 'original_canonical') {
      throw new \RuntimeException('Reviewed interpretive art must attach to its exact room without claiming tactical alignment.');
    }
    $runtime_room = $projection->buildRuntimeRoom([
      'room_id' => $room_id,
      'room_version_id' => $version_id,
      'row' => ['room_id' => $room_id, 'version_id' => $version_id, 'version' => '1.0.0'],
      'room_payload' => $published_room,
    ], ['runtime_room_id' => (string) $placement['placement_id'], 'placement' => $placement]);
    $hexes = $runtime_room['_layout_data']['hexes'] ?? [];
    $items = $runtime_room['_contents_data']['items'] ?? [];
    if (count($hexes) < 16 || count($items) !== 1
      || count((array) ($runtime_room['entry_points'] ?? [])) !== 1
      || count((array) ($runtime_room['exit_points'] ?? [])) !== ($resource_id === array_key_last($scene_definitions) ? 0 : 1)
      || ($runtime_room['metadata']['module_conversion']['resource_id'] ?? NULL) !== $resource_id
      || ($runtime_room['metadata']['campaign_source']['room_version_id'] ?? NULL) !== $version_id) {
      throw new \RuntimeException('Pinned scene room does not project into the expected canonical runtime content.');
    }
    foreach ($hexes as $hex) {
      if (!preg_match('/^[0-9a-f]{15}$/', (string) ($hex['h3_index_res14'] ?? ''))) {
        throw new \RuntimeException('Projected source-scene room contains a hex without a canonical H3 res14 index.');
      }
    }
  }
  echo "Ten-scene staged-map assembly, connected traversal, canonical room projection, and 15,532 native receipts passed in rollback-only mode; gameplay publication remains gated.\n";
}
finally {
  try {
    $rows = $database->select('dc_generated_images', 'i')
      ->fields('i', ['id', 'file_uri'])
      ->condition('provider', 'module-import')
      ->condition('provider_request_id', $inventory_hash)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $trial_rows = array_values(array_filter($rows, static fn(array $row): bool => !isset($existing_image_ids[(int) $row['id']])));
    $map_staging->discardSceneAssets(array_map(static fn(array $row): array => [
      'image_id' => (int) $row['id'],
      'file_uri' => $row['file_uri'],
      'license_notice_uri' => $row['file_uri'] . '.LICENSE.txt',
    ], $trial_rows));
  }
  finally {
    $transaction->rollBack();
  }
}
