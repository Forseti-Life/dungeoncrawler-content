<?php

/**
 * Rollback-only original fixture conversion through canonical publication.
 *
 * Run from the site's root with:
 * php -d memory_limit=2G vendor/drush/drush/drush.php --no-interaction php:script /home/ubuntu/forseti.life/dungeoncrawler-content/tests/module_conversion_generic_fixture_release_audit_test.php
 */

$root = realpath(__DIR__ . '/fixtures/module_conversion/forseti-lantern-conservatory');
if (!is_string($root)) {
  throw new \RuntimeException('Project-authored conversion fixture is missing.');
}
$archive = tempnam(sys_get_temp_dir(), 'original-module-audit-');
if ($archive === FALSE) {
  throw new \RuntimeException('Could not create a temporary fixture archive.');
}
$zip = new \ZipArchive();
if ($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
  unlink($archive);
  throw new \RuntimeException('Could not open a temporary fixture archive.');
}
try {
  $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
  foreach ($files as $file) {
    $relative = substr($file->getPathname(), strlen($root) + 1);
    if (!$zip->addFile($file->getPathname(), $relative)) {
      throw new \RuntimeException('Could not add fixture file to archive.');
    }
  }
}
finally {
  $zip->close();
}

$transaction = \Drupal::database()->startTransaction();
$created_image_uris = [];
try {
  $inspector = \Drupal::service('dungeoncrawler_content.module_conversion.archive_inspector');
  $planner = \Drupal::service('dungeoncrawler_content.module_conversion.plan');
  $releases = \Drupal::service('dungeoncrawler_content.module_conversion.release');
  $definitions = \Drupal::service('dungeoncrawler_content.canonical_definitions');
  $inventory = $inspector->inspectArchive($archive);
  $plan = $planner->buildPlan($inventory);
  $release = $releases->registerRelease($inventory, $plan, NULL, 1, [
    'conversion_revision' => 'rollback-original-' . bin2hex(random_bytes(5)),
  ]);
  $release_id = $release['release_id'];
  $scenes = [];
  $other = [];
  $adventure = NULL;
  $reviewed_resource = NULL;
  foreach ($plan['resources'] as $resource) {
    if ($resource['resource_type'] === 'scene') {
      $scenes[$resource['resource_id']] = $resource;
    }
    elseif ($resource['resource_type'] === 'adventure') {
      $adventure = $resource;
    }
    elseif ($resource['resource_type'] === 'embedded_item' && $resource['disposition'] === 'requires_authoring') {
      $reviewed_resource = $resource;
    }
    else {
      $other[] = $resource['resource_id'];
    }
  }
  if (count($scenes) !== 3 || $adventure === NULL || $other === [] || $reviewed_resource === NULL) {
    throw new \RuntimeException('Fixture must have three rooms, one adventure, an authorable item, and other native resources.');
  }
  $reviewed_item_id = 'original-reviewed-fixture-' . bin2hex(random_bytes(5));
  $reviewed_item = $definitions->saveDefinition('item', NULL, [
    'schema_version' => '1.0.0',
    'item_id' => $reviewed_item_id,
    'name' => 'Original Review Note',
    'item_type' => 'adventuring_gear',
    'level' => 0,
    'rarity' => 'common',
    'description' => 'Original review content with no dependency on an incomplete source category.',
  ]);
  $releases->bindReviewedDefinition($release_id, [
    'resource_id' => $reviewed_resource['resource_id'],
    'expected_plan_hash' => $plan['plan_hash'],
    'family' => 'item',
    'definition_id' => $reviewed_item_id,
    'version' => $reviewed_item['version'],
    'rights_status' => 'project_original',
    'review_confirmed' => TRUE,
  ], 1);
  $other_run = $releases->executePlannedCommands($release_id, [
    'resource_ids' => $other,
    'publish_version' => '1.0.0',
    'register_receipts' => TRUE,
  ], 1);
  if (($other_run['run']['succeeded'] ?? NULL) !== count($other) || ($other_run['run']['failed'] ?? NULL) !== 0) {
    throw new \RuntimeException('Fixture resource receipts were not all registered.');
  }
  $scene_definitions = [];
  $objectives = [];
  $quest_template_id = 'original_fixture_' . bin2hex(random_bytes(5));
  foreach ($scenes as $resource_id => $scene) {
    $index = count($scene_definitions) + 1;
    $canvas = imagecreatetruecolor(640, 480);
    if (!$canvas instanceof \GdImage) {
      throw new \RuntimeException('Fixture image renderer is unavailable.');
    }
    $ground = imagecolorallocate($canvas, 24 + 15 * $index, 53 + 20 * $index, 62 + 10 * $index);
    $path_color = imagecolorallocate($canvas, 176, 155, 107);
    imagefilledrectangle($canvas, 0, 0, 639, 479, $ground);
    imagefilledellipse($canvas, 320, 240, 430, 240, $path_color);
    ob_start();
    imagepng($canvas);
    $original_png = ob_get_clean();
    imagedestroy($canvas);
    if (!is_string($original_png) || $original_png === '') {
      throw new \RuntimeException('Fixture original map image was not rendered.');
    }
    $stored_image = \Drupal::service('dungeoncrawler_content.generated_image_repository')->persistGeneratedImage([
      'success' => TRUE,
      'provider' => 'project-original-fixture',
      'provider_model' => 'project-authored',
      'request_id' => 'fixture-' . bin2hex(random_bytes(5)),
      'output' => ['image_data_uri' => 'data:image/png;base64,' . base64_encode($original_png)],
      'payload' => ['prompt' => 'Original abstract fixture map', 'requested_by_uid' => 1],
    ]);
    if (($stored_image['stored'] ?? FALSE) !== TRUE || !is_int($stored_image['image_id'] ?? NULL)) {
      throw new \RuntimeException('Original fixture map image could not be stored.');
    }
    $stored_row = \Drupal::database()->select('dc_generated_images', 'i')
      ->fields('i', ['sha256', 'file_uri'])
      ->condition('id', $stored_image['image_id'])
      ->execute()->fetchAssoc();
    if (!is_array($stored_row) || !is_string($stored_row['sha256'] ?? NULL)
      || !is_string($stored_row['file_uri'] ?? NULL)
      || !str_starts_with($stored_row['file_uri'], 'public://generated-images/')) {
      throw new \RuntimeException('Original fixture image storage is incomplete.');
    }
    $created_image_uris[] = $stored_row['file_uri'];
    $item_id = 'original-fixture-waymark-' . bin2hex(random_bytes(5));
    $definitions->saveDefinition('item', NULL, [
      'schema_version' => '1.0.0',
      'item_id' => $item_id,
      'name' => 'Original Waymark ' . $index,
      'item_type' => 'adventuring_gear',
      'level' => 0,
      'rarity' => 'common',
      'description' => 'A project-authored travel marker for the review fixture.',
    ]);
    $scene_definitions[$resource_id] = [
      'metadata' => [
        'name' => 'Original Review Chamber ' . $index,
        'description' => 'A project-authored chamber on a connected exploration route.',
        'theme' => 'exploration',
        'rights_status' => 'project_original',
      ],
      'definitions' => [[
        'family' => 'item',
        'definition_id' => $item_id,
        'version' => '1.0.0',
        'rights_status' => 'project_original',
      ]],
      'original_map' => [
        'image_id' => $stored_image['image_id'],
        'sha256' => $stored_row['sha256'],
        'rights_status' => 'project_original',
        'review_notes' => 'This project-authored abstract image is an original illustration; it does not reproduce or trace a source map.',
      ],
    ];
    if (array_intersect(['width', 'height', 'grid_size'], $scene['source']['missing_source_fields']) !== []) {
      $scene_definitions[$resource_id]['original_geometry'] = [
        'size_category' => 'medium',
        'basis' => 'invented_for_missing_source_geometry',
        'rights_status' => 'project_original',
        'review_notes' => 'This original room size is intentionally invented; no source dimensions were supplied.',
      ];
    }
    $objectives[$resource_id] = [
      'description' => 'Survey the original review chamber ' . $index . '.',
      'next_step' => 'Follow the marked passage to the next chamber.',
      'interaction' => [
        'description' => 'Inspect the original waymark ' . $index . '.',
        'item_definition_id' => $item_id,
      ],
    ];
  }
  try {
    $run = $releases->executePlannedCommands($release_id, [
    'publish_version' => '1.0.0',
    'register_receipts' => TRUE,
    'original_scene_generation' => [
      'scene_definitions' => $scene_definitions,
      'terminal_scene_resource_id' => array_key_last($scenes),
      'dungeon' => [
        'name' => 'Original Fixture Exploration',
        'description' => 'A project-authored, connected exploration validation route.',
        'theme' => 'exploration',
        'rights_status' => 'project_original',
      ],
      'quest' => [
        'template_id' => $quest_template_id,
        'name' => 'Survey the Original Fixture',
        'description' => 'A project-authored exploration and interaction route.',
        'rights_status' => 'project_original',
        'objectives' => $objectives,
      ],
      'seed' => 23,
    ],
    ], 1);
  }
  catch (\Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionReadException $exception) {
    echo json_encode($exception->getFindings(), JSON_THROW_ON_ERROR) . PHP_EOL;
    throw $exception;
  }
  if (($run['run']['succeeded'] ?? NULL) !== 4 || ($run['run']['failed'] ?? NULL) !== 0) {
    throw new \RuntimeException('Original room/dungeon generation did not complete.');
  }
  $receipt = $run['release']['mapping']['native_receipts'][$adventure['resource_id']] ?? NULL;
  try {
    $published = $releases->registerPublishedManifest($release_id, [
      'dungeon_id' => $receipt['target_id'] ?? '',
      'version_id' => $receipt['target_version'] ?? '',
      'receipt_resource_id' => $adventure['resource_id'],
    ], 1);
  }
  catch (\Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionReadException $exception) {
    echo json_encode($exception->getFindings(), JSON_THROW_ON_ERROR) . PHP_EOL;
    throw $exception;
  }
  if (($published['publication_status'] ?? NULL) !== 'published') {
    throw new \RuntimeException('Verified original fixture release was not publishable.');
  }
  $coverage = $releases->sceneCoverageReport($release_id);
  if (($coverage['complete'] ?? FALSE) !== TRUE || ($coverage['quest_verified'] ?? FALSE) !== TRUE
    || ($coverage['scene_count'] ?? NULL) !== 3
    || ($run['release']['mapping']['native_receipt_summary']['complete'] ?? FALSE) !== TRUE) {
    throw new \RuntimeException('Project-authored fixture did not satisfy scene, quest, and receipt coverage.');
  }
  $resolved = $releases->resolveCampaignSource($release_id);
  if (($resolved['release_id'] ?? NULL) !== $release_id) {
    throw new \RuntimeException('Published project-authored fixture was not launchable.');
  }
  $database = \Drupal::database();
  $campaign_id = \Drupal::service('dungeoncrawler_content.campaign_initialization')->initializeCampaign(
    1, 'Original fixture player audit', 'island_exploration', 'normal',
    ['kind' => 'module_release', 'release_id' => $release_id]
  );
  $dungeon_row = $database->select('dc_campaign_dungeons', 'd')
    ->fields('d', ['dungeon_data'])
    ->condition('d.campaign_id', $campaign_id)->range(0, 1)->execute()->fetchAssoc();
  if (!is_array($dungeon_row)) {
    throw new \RuntimeException('Original fixture did not materialize a player dungeon.');
  }
  $dungeon = json_decode((string) $dungeon_row['dungeon_data'], TRUE, 512, JSON_THROW_ON_ERROR);
  $entrance = $dungeon['active_room_id'] ?? NULL;
  $source_player = $database->select('dc_campaign_characters', 'c')
    ->fields('c')
    ->condition('c.uid', 1)
    ->condition('c.role', 'player')
    ->condition('c.source_character_id', 0, '>')
    ->orderBy('c.id', 'DESC')->range(0, 1)->execute()->fetchAssoc();
  if (!is_array($source_player) || !is_string($entrance)) {
    throw new \RuntimeException('Original fixture player prerequisites are unavailable.');
  }
  unset($source_player['id']);
  $source_player['campaign_id'] = $campaign_id;
  $source_player['instance_id'] = \Drupal::service('uuid')->generate();
  $source_player['uuid'] = \Drupal::service('uuid')->generate();
  $source_player['location_type'] = 'room';
  $source_player['location_ref'] = $entrance;
  $source_player['last_room_id'] = $entrance;
  $source_player['name'] = 'Original fixture player';
  $source_player['is_active'] = 1;
  $character_id = (int) $database->insert('dc_campaign_characters')->fields($source_player)->execute();
  $quest = $database->select('dc_campaign_quests', 'q')
    ->fields('q', ['quest_id', 'generated_objectives'])
    ->condition('q.campaign_id', $campaign_id)
    ->condition('q.source_template_id', $quest_template_id)
    ->range(0, 1)->execute()->fetchAssoc();
  if (!is_array($quest) || !\Drupal::service('dungeoncrawler_content.storyline_quest_lifecycle')
    ->startOfferedQuest($campaign_id, (string) $quest['quest_id'], $character_id)) {
    throw new \RuntimeException('Original fixture quest was not available to the player.');
  }
  $entrance_marker = NULL;
  $other_marker = NULL;
  $other_room = NULL;
  $markers = [];
  foreach ($dungeon['rooms'] as $room) {
    if (is_string($room['room_id'] ?? NULL) && is_string($room['entities'][0]['instance_id'] ?? NULL)) {
      $markers[$room['room_id']] = $room['entities'][0]['instance_id'];
    }
    if (($room['room_id'] ?? NULL) === $entrance) {
      $entrance_marker = $room['entities'][0]['instance_id'] ?? NULL;
    }
    else {
      $other_marker ??= $room['entities'][0]['instance_id'] ?? NULL;
      $other_room ??= $room['room_id'] ?? NULL;
    }
  }
  if (!is_string($entrance_marker) || !is_string($other_marker) || !is_string($other_room)) {
    throw new \RuntimeException('Original fixture did not project exact-room item interactions.');
  }
  $coordinator = \Drupal::service('dungeoncrawler_content.game_coordinator');
  (new \ReflectionProperty($coordinator, 'narrationEngine'))->setValue($coordinator, NULL);
  $invalid = $coordinator->processAction($campaign_id, [
    'type' => 'interact',
    'actor' => $source_player['instance_id'],
    'target' => $other_marker,
  ]);
  if (!empty($invalid['success'])) {
    throw new \RuntimeException('An off-room interaction was accepted.');
  }
  $interaction = $coordinator->processAction($campaign_id, [
    'type' => 'interact',
    'actor' => $source_player['instance_id'],
    'target' => $entrance_marker,
  ]);
  if (empty($interaction['success']) || empty($interaction['result']['quest_touchpoint']['success'])
    || ($interaction['result']['quest_touchpoint']['decision'] ?? NULL) !== 'APPLY_PROGRESS') {
    throw new \RuntimeException('The original fixture waymark did not advance its authored quest.');
  }
  $navigation = \Drupal::service('dungeoncrawler_content.navigation_service');
  $route = $navigation->resolveRoomRoutePlan($dungeon, $entrance, $other_room);
  if (!is_array($route) || count($route['path_room_ids'] ?? []) < 2) {
    throw new \RuntimeException('The generated fixture rooms are not connected for player travel.');
  }
  $next = $route['path_room_ids'][1];
  $exits = $navigation->buildNavigationCapabilitiesWithRoadNetwork($dungeon, $entrance);
  $exit = array_values(array_filter($exits, static fn(array $capability): bool => ($capability['target_room_id'] ?? NULL) === $next))[0] ?? NULL;
  if (!is_array($exit) || empty($exit['available'])) {
    throw new \RuntimeException('The fixture player cannot select the next generated room.');
  }
  $transition = $coordinator->processAction($campaign_id, [
    'type' => 'transition',
    'actor' => $source_player['instance_id'],
    'params' => [
      'character_id' => $character_id,
      'target_room_id' => $next,
      'connection_id' => $exit['connection_id'],
      'target_hex' => $exit['origin_hex'],
    ],
  ]);
  if (empty($transition['success']) || ($transition['active_room_id'] ?? NULL) !== $next) {
    throw new \RuntimeException('Original fixture player transition failed.');
  }
  $interact_in_room = static function (string $room_id) use ($markers, $coordinator, $campaign_id, $source_player): void {
    if (!isset($markers[$room_id])) {
      throw new \RuntimeException('Original fixture room has no reviewed item marker.');
    }
    $response = $coordinator->processAction($campaign_id, [
      'type' => 'interact',
      'actor' => $source_player['instance_id'],
      'target' => $markers[$room_id],
    ]);
    if (empty($response['success']) || empty($response['result']['quest_touchpoint']['success'])
      || ($response['result']['quest_touchpoint']['decision'] ?? NULL) !== 'APPLY_PROGRESS') {
      throw new \RuntimeException('A generated fixture item did not advance its exact-room quest objective.');
    }
  };
  $interact_in_room($next);
  $current_room = $next;
  $visited = [$entrance => TRUE, $next => TRUE];
  foreach ($dungeon['rooms'] as $room) {
    $destination = $room['room_id'] ?? NULL;
    if (!is_string($destination) || isset($visited[$destination])) {
      continue;
    }
    $route = $navigation->resolveRoomRoutePlan($dungeon, $current_room, $destination);
    if (!is_array($route)) {
      throw new \RuntimeException('No player route to one of the original fixture rooms.');
    }
    foreach (array_slice($route['path_room_ids'], 1) as $room_id) {
      $dungeon['active_room_id'] = $current_room;
      $exits = $navigation->buildNavigationCapabilitiesWithRoadNetwork($dungeon, $current_room);
      $exit = array_values(array_filter($exits, static fn(array $capability): bool => ($capability['target_room_id'] ?? NULL) === $room_id))[0] ?? NULL;
      if (!is_array($exit) || empty($exit['available'])) {
        throw new \RuntimeException('An original fixture room exit is unavailable to the player.');
      }
      $moved = $coordinator->processAction($campaign_id, [
        'type' => 'transition',
        'actor' => $source_player['instance_id'],
        'params' => [
          'character_id' => $character_id,
          'target_room_id' => $room_id,
          'connection_id' => $exit['connection_id'],
          'target_hex' => $exit['origin_hex'],
        ],
      ]);
      if (empty($moved['success']) || ($moved['active_room_id'] ?? NULL) !== $room_id) {
        throw new \RuntimeException('A player transition to an original fixture room failed.');
      }
      $current_room = $room_id;
      if (!isset($visited[$room_id])) {
        $interact_in_room($room_id);
        $visited[$room_id] = TRUE;
      }
    }
  }
  if (count($visited) !== 3) {
    throw new \RuntimeException('The fixture player did not reach all three rooms.');
  }
  $progress_json = $database->select('dc_campaign_quest_progress', 'p')
    ->fields('p', ['objective_states'])
    ->condition('campaign_id', $campaign_id)
    ->condition('quest_id', $quest['quest_id'])
    ->condition('character_id', $character_id)
    ->execute()->fetchField();
  if (!is_string($progress_json)) {
    throw new \RuntimeException('The authored fixture quest lost its player progress.');
  }
  $objective_states = json_decode($progress_json, TRUE, 512, JSON_THROW_ON_ERROR);
  $interaction_count = 0;
  foreach ($objective_states as $phase) {
    foreach ($phase['objectives'] ?? [] as $objective) {
      if (($objective['type'] ?? NULL) === 'interact' && !empty($objective['completed'])) {
        $interaction_count++;
      }
    }
  }
  if ($interaction_count !== 3) {
    throw new \RuntimeException('Not all three original fixture item objectives completed for the player.');
  }
  echo 'Generic fixture: receipts=' . count($run['release']['mapping']['native_receipts'])
    . ', reviewed_binding=yes, scenes=3, quest_verified=yes, item_objectives=' . $interaction_count . ', player_rooms_visited=' . count($visited) . ' (all changes rolled back).' . PHP_EOL;
}
finally {
  $transaction->rollBack();
  foreach ($created_image_uris as $uri) {
    \Drupal::service('file_system')->delete($uri);
  }
  unlink($archive);
}
