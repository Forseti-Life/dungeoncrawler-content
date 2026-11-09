<?php

/**
 * Rollback-only publication and player audit of the original Island story.
 *
 * Run with the site-local Drush and a 2 GB PHP limit. This script neither
 * publishes the release nor retains the trial campaign.
 */

$database = \Drupal::database();
$row = $database->select('dungeoncrawler_content_module_releases', 'r')
  ->fields('r', ['release_id', 'publication_status', 'mapping_data'])
  ->condition('module_id', 'the-island-adventure')
  ->condition('module_version', '1.1.0')
  ->condition('conversion_revision', 'island-original-story-v1')
  ->execute()->fetchAssoc();
if (!is_array($row) || !in_array($row['publication_status'], ['receipts_recorded', 'published'], TRUE)) {
  throw new \RuntimeException('The immutable original-story revision is not staged or published.');
}
$original_status = $row['publication_status'];
$release_id = $row['release_id'];
$mapping = json_decode($row['mapping_data'], TRUE, 512, JSON_THROW_ON_ERROR);
$receipts = $mapping['native_receipts'] ?? [];
$dungeon_receipts = array_values(array_filter($receipts, static fn(array $receipt): bool =>
  ($receipt['owner'] ?? NULL) === 'DungeonEditorService'));
$room_receipts = array_values(array_filter($receipts, static fn(array $receipt): bool =>
  ($receipt['owner'] ?? NULL) === 'RoomEditorService'));
if (count($receipts) !== 15532 || count($dungeon_receipts) !== 1 || count($room_receipts) !== 10
  || ($mapping['native_receipt_summary']['complete'] ?? FALSE) !== TRUE) {
  throw new \RuntimeException('The original story must have complete native receipts and ten exact room pins.');
}
$dungeon_receipt = $dungeon_receipts[0];
$manifest = [
  'dungeon_id' => $dungeon_receipt['target_id'],
  'version_id' => $dungeon_receipt['target_version'],
  'receipt_resource_id' => $dungeon_receipt['resource_id'],
];
$template_id = $dungeon_receipt['source_receipt']['quest_template_id'] ?? NULL;
if ($template_id !== 'island-original-story-v1') {
  throw new \RuntimeException('The dungeon does not pin its original-story quest.');
}
$dungeon_json = $database->select('dungeoncrawler_content_dungeon_versions', 'v')
  ->fields('v', ['dungeon_payload'])
  ->condition('dungeon_id', $manifest['dungeon_id'])
  ->condition('version_id', $manifest['version_id'])
  ->execute()->fetchField();
if (!is_string($dungeon_json)) {
  throw new \RuntimeException('The immutable original-story dungeon is missing.');
}
$pinned_dungeon = json_decode($dungeon_json, TRUE, 512, JSON_THROW_ON_ERROR);
$placements = $pinned_dungeon['room_placements'] ?? [];
$room_pins = array_column($room_receipts, 'target_version', 'target_id');
$items = [];
foreach ($placements as $placement) {
  if (($room_pins[$placement['room_id'] ?? ''] ?? NULL) !== ($placement['version_id'] ?? NULL)) {
    throw new \RuntimeException('A story room is not pinned to its exact receipt version.');
  }
  $room_json = $database->select('dungeoncrawler_content_room_versions', 'v')
    ->fields('v', ['room_payload'])
    ->condition('room_id', $placement['room_id'])
    ->condition('version_id', $placement['version_id'])
    ->execute()->fetchField();
  if (!is_string($room_json)) {
    throw new \RuntimeException('A pinned story room is missing.');
  }
  $room = json_decode($room_json, TRUE, 512, JSON_THROW_ON_ERROR);
  $objects = array_values(array_filter($room['placements'] ?? [], static fn(array $object): bool =>
    ($object['definition_ref']['family'] ?? NULL) === 'item'));
  $asset = $room['metadata']['module_conversion']['source_scene']['map_assets']['background'] ?? [];
  if (count($objects) !== 1 || ($asset['asset_kind'] ?? NULL) !== 'generated_original_scene_map'
    || !is_int($asset['image_id'] ?? NULL) || !is_string($asset['sha256'] ?? NULL)) {
    throw new \RuntimeException('A story room lacks its unique item or project-original map.');
  }
  $item_id = $objects[0]['definition_ref']['definition_id'];
  if (isset($items[$item_id])) {
    throw new \RuntimeException('Original-story item definitions must be distinct across rooms.');
  }
  $items[$item_id] = TRUE;
}
if (count($placements) !== 10 || count($items) !== 10) {
  throw new \RuntimeException('The original story must cover ten distinct pinned rooms and items.');
}
unset($mapping, $receipts, $row, $room_json, $dungeon_json);

$transaction = $database->startTransaction();
try {
  $releases = \Drupal::service('dungeoncrawler_content.module_conversion.release');
  if ($original_status !== 'published') {
    $published = $releases->registerPublishedManifest($release_id, $manifest, 1);
    if (($published['publication_status'] ?? NULL) !== 'published') {
      throw new \RuntimeException('Publication rejected the complete original-story manifest.');
    }
  }
  $coverage = $releases->sceneCoverageReport($release_id);
  if (empty($coverage['complete']) || empty($coverage['quest_verified'])
    || ($coverage['scene_count'] ?? NULL) !== 10
    || ($releases->resolveCampaignSource($release_id)['version_id'] ?? NULL) !== $manifest['version_id']) {
    throw new \RuntimeException('The original-story release failed publication or exact-room quest coverage.');
  }
  $campaign_id = \Drupal::service('dungeoncrawler_content.campaign_initialization')->initializeCampaign(
    1, 'Original Island story audit', 'island_exploration', 'normal',
    ['kind' => 'module_release', 'release_id' => $release_id]
  );
  $row = $database->select('dc_campaign_dungeons', 'd')
    ->fields('d', ['dungeon_data'])
    ->condition('campaign_id', $campaign_id)->range(0, 1)->execute()->fetchAssoc();
  if (!is_array($row)) {
    throw new \RuntimeException('The original-story campaign has no runtime dungeon.');
  }
  $runtime = json_decode($row['dungeon_data'], TRUE, 512, JSON_THROW_ON_ERROR);
  $entrance = $runtime['active_room_id'] ?? NULL;
  $character = $database->select('dc_campaign_characters', 'c')
    ->fields('c')->condition('uid', 1)->condition('role', 'player')
    ->condition('source_character_id', 0, '>')
    ->orderBy('c.id', 'DESC')->range(0, 1)->execute()->fetchAssoc();
  if (!is_array($character) || !is_string($entrance)) {
    throw new \RuntimeException('The original-story campaign has no player or entrance.');
  }
  unset($character['id']);
  $character['campaign_id'] = $campaign_id;
  $character['instance_id'] = \Drupal::service('uuid')->generate();
  $character['uuid'] = \Drupal::service('uuid')->generate();
  $character['location_type'] = 'room';
  $character['location_ref'] = $entrance;
  $character['last_room_id'] = $entrance;
  $character['name'] = 'Original-story audit player';
  $character['is_active'] = 1;
  $character_id = (int) $database->insert('dc_campaign_characters')->fields($character)->execute();
  $quest = $database->select('dc_campaign_quests', 'q')
    ->fields('q', ['quest_id'])
    ->condition('campaign_id', $campaign_id)
    ->condition('source_template_id', $template_id)
    ->range(0, 1)->execute()->fetchAssoc();
  if (!is_array($quest) || !\Drupal::service('dungeoncrawler_content.storyline_quest_lifecycle')
    ->startOfferedQuest($campaign_id, $quest['quest_id'], $character_id)) {
    throw new \RuntimeException('The original-story quest was not offered or activated.');
  }
  $markers = [];
  foreach ($runtime['rooms'] as $room) {
    if (!is_string($room['room_id'] ?? NULL)
      || !is_string($room['entities'][0]['instance_id'] ?? NULL)) {
      throw new \RuntimeException('A runtime story room lost its item marker.');
    }
    $markers[$room['room_id']] = $room['entities'][0]['instance_id'];
  }
  if (count($markers) !== 10 || !isset($markers[$entrance])) {
    throw new \RuntimeException('The player cannot reach all ten story markers.');
  }
  $coordinator = \Drupal::service('dungeoncrawler_content.game_coordinator');
  (new \ReflectionProperty($coordinator, 'narrationEngine'))->setValue($coordinator, NULL);
  $other = array_key_first(array_diff_key($markers, [$entrance => TRUE]));
  $off_room = $coordinator->processAction($campaign_id, [
    'type' => 'interact', 'actor' => $character['instance_id'], 'target' => $markers[$other],
  ]);
  if (!empty($off_room['success'])) {
    throw new \RuntimeException('An off-room original-story interaction was accepted.');
  }
  $interact = static function (string $room_id) use ($markers, $coordinator, $campaign_id, $character): void {
    try {
      $result = $coordinator->processAction($campaign_id, [
        'type' => 'interact', 'actor' => $character['instance_id'], 'target' => $markers[$room_id],
      ]);
    }
    catch (\Throwable $e) {
      throw new \RuntimeException('Original-story interaction failed in ' . $room_id . ': ' . $e->getMessage(), 0, $e);
    }
    if (empty($result['success']) || ($result['result']['quest_touchpoint']['decision'] ?? NULL) !== 'APPLY_PROGRESS') {
      throw new \RuntimeException('The exact-room original-story item objective did not progress: ' . $room_id);
    }
  };
  $interact($entrance);
  $visited = [$entrance => TRUE];
  $current = $entrance;
  $navigation = \Drupal::service('dungeoncrawler_content.navigation_service');
  foreach ($runtime['rooms'] as $room) {
    $destination = $room['room_id'];
    if (isset($visited[$destination])) {
      continue;
    }
    $route = $navigation->resolveRoomRoutePlan($runtime, $current, $destination);
    if (!is_array($route) || count($route['path_room_ids'] ?? []) < 2) {
      throw new \RuntimeException('An original-story room is unreachable.');
    }
    foreach (array_slice($route['path_room_ids'], 1) as $next) {
      $runtime['active_room_id'] = $current;
      $exits = $navigation->buildNavigationCapabilitiesWithRoadNetwork($runtime, $current);
      $exit = array_values(array_filter($exits, static fn(array $capability): bool =>
        ($capability['target_room_id'] ?? NULL) === $next))[0] ?? NULL;
      if (!is_array($exit) || empty($exit['available'])) {
        throw new \RuntimeException('A player exit is unavailable on the original route.');
      }
      try {
        $moved = $coordinator->processAction($campaign_id, [
          'type' => 'transition',
          'actor' => $character['instance_id'],
          'params' => [
            'character_id' => $character_id,
            'target_room_id' => $next,
            'connection_id' => $exit['connection_id'],
            'target_hex' => $exit['origin_hex'],
          ],
        ]);
      }
      catch (\Throwable $e) {
        throw new \RuntimeException('Original-story transition failed from ' . $current . ' to ' . $next . ': ' . $e->getMessage(), 0, $e);
      }
      if (empty($moved['success']) || ($moved['active_room_id'] ?? NULL) !== $next) {
        throw new \RuntimeException('Player travel failed on the original route.');
      }
      $current = $next;
      if (!isset($visited[$next])) {
        $interact($next);
        $visited[$next] = TRUE;
      }
    }
  }
  $states_json = $database->select('dc_campaign_quest_progress', 'p')
    ->fields('p', ['objective_states'])
    ->condition('campaign_id', $campaign_id)
    ->condition('quest_id', $quest['quest_id'])
    ->condition('character_id', $character_id)
    ->execute()->fetchField();
  if (!is_string($states_json)) {
    throw new \RuntimeException('The original-story quest lost its player progress.');
  }
  $states = json_decode($states_json, TRUE, 512, JSON_THROW_ON_ERROR);
  $completed_items = 0;
  $completed_exploration = 0;
  foreach ($states as $phase) {
    foreach ($phase['objectives'] ?? [] as $objective) {
      $completed_items += (int) (($objective['type'] ?? NULL) === 'interact' && !empty($objective['completed']));
      $completed_exploration += (int) (($objective['type'] ?? NULL) === 'explore' && !empty($objective['completed']));
    }
  }
  $quest_status = $database->select('dc_campaign_quests', 'q')
    ->fields('q', ['status'])
    ->condition('campaign_id', $campaign_id)
    ->condition('quest_id', $quest['quest_id'])->execute()->fetchField();
  if (count($visited) !== 10 || $completed_items !== 10
    || $completed_exploration !== 10 || $quest_status !== 'completed') {
    throw new \RuntimeException('The player did not complete the ten-room, twenty-objective original quest.');
  }
  echo 'Original Island story: rooms=10, distinct_items=10, completed_exploration=' . $completed_exploration
    . ', completed_item_objectives=' . $completed_items
    . ', native_receipts=15532, publication/player trial rolled back.' . PHP_EOL;
}
finally {
  $transaction->rollBack();
}
if ($database->select('dungeoncrawler_content_module_releases', 'r')
  ->fields('r', ['publication_status'])->condition('release_id', $release_id)
  ->execute()->fetchField() !== $original_status) {
  throw new \RuntimeException('The trial unexpectedly changed the persistent release status.');
}
