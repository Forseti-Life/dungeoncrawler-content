<?php

/**
 * Fresh-campaign owner/familiar navigation smoke trial; all writes roll back.
 *
 * Run: drush php:script tests/new_campaign_follower_navigation_smoke.php
 */

$db = \Drupal::database();
$transaction = $db->startTransaction();
try {
  $campaign_id = \Drupal::service('dungeoncrawler_content.campaign_initialization')->initializeCampaign(
    1,
    'Follower navigation rollback trial',
    'island_exploration',
    'normal',
    ['kind' => 'module_release', 'release_id' => '04764018-1b96-41a8-a5ad-99ec66220a0c']
  );
  $dungeon_row = $db->select('dc_campaign_dungeons', 'd')
    ->fields('d', ['dungeon_id', 'dungeon_data'])
    ->condition('campaign_id', $campaign_id)
    ->range(0, 1)->execute()->fetchAssoc();
  $dungeon = json_decode((string) $dungeon_row['dungeon_data'], TRUE, 512, JSON_THROW_ON_ERROR);
  $origin = (string) $dungeon['active_room_id'];
  $owner = $db->select('dc_campaign_characters', 'c')
    ->fields('c')
    ->condition('id', 5531)
    ->execute()->fetchAssoc();
  $familiar = $db->select('dc_campaign_characters', 'c')
    ->fields('c')
    ->condition('id', 5532)
    ->execute()->fetchAssoc();
  if (!is_array($owner) || !is_array($familiar)
    || (int) $owner['source_character_id'] !== (int) $familiar['source_character_id']) {
    throw new \RuntimeException('Smoke fixture owner/familiar source rows are unavailable.');
  }
  foreach ([$owner, $familiar] as $index => $source) {
    unset($source['id']);
    $source['campaign_id'] = $campaign_id;
    $source['instance_id'] = $index === 0
      ? "pc-{$campaign_id}-{$owner['source_character_id']}"
      : (string) $familiar['instance_id'];
    $source['uuid'] = \Drupal::service('uuid')->generate();
    $source['location_type'] = 'room';
    $source['location_ref'] = $origin;
    $source['last_room_id'] = $origin;
    $source['position_h3'] = NULL;
    $source['position_q'] = 0;
    $source['position_r'] = 0;
    $source['is_active'] = 1;
    $inserted = (int) $db->insert('dc_campaign_characters')->fields($source)->execute();
    if ($index === 0) {
      $owner_id = $inserted;
      $actor_id = $source['instance_id'];
    }
  }
  $coordinator = \Drupal::service('dungeoncrawler_content.game_coordinator');
  (new \ReflectionProperty($coordinator, 'narrationEngine'))->setValue($coordinator, NULL);
  $navigation = \Drupal::service('dungeoncrawler_content.navigation_service');
  $exits = $navigation->buildNavigationCapabilitiesWithRoadNetwork($dungeon, $origin);
  $exit = NULL;
  foreach ($exits as $candidate) {
    if (!empty($candidate['available']) && !empty($candidate['is_discovered'])) {
      $exit = $candidate;
      break;
    }
  }
  if (!is_array($exit)) {
    throw new \RuntimeException('Smoke fixture start has no available discovered exit.');
  }
  $destination = (string) $exit['target_room_id'];
  $response = $coordinator->processAction($campaign_id, [
    'type' => 'transition',
    'actor' => $actor_id,
    'params' => [
      'character_id' => $owner_id,
      'target_room_id' => $destination,
      'connection_id' => (string) $exit['connection_id'],
      'target_hex' => $exit['origin_hex'],
    ],
  ]);
  if (empty($response['success'])) {
    throw new \RuntimeException('Fresh-campaign transition failed: ' . json_encode($response['error'] ?? NULL));
  }
  $locations = $db->select('dc_campaign_characters', 'c')
    ->fields('c', ['instance_id', 'last_room_id'])
    ->condition('campaign_id', $campaign_id)
    ->condition('source_character_id', (int) $owner['source_character_id'])
    ->execute()->fetchAllKeyed();
  $campaign_data = json_decode((string) $db->select('dc_campaigns', 'c')
    ->fields('c', ['campaign_data'])->condition('id', $campaign_id)->execute()->fetchField(), TRUE, 512, JSON_THROW_ON_ERROR);
  $stored_dungeon = json_decode((string) $db->select('dc_campaign_dungeons', 'd')
    ->fields('d', ['dungeon_data'])->condition('campaign_id', $campaign_id)->execute()->fetchField(), TRUE, 512, JSON_THROW_ON_ERROR);
  if (($locations[$actor_id] ?? NULL) !== $destination
    || ($locations[$familiar['instance_id']] ?? NULL) !== $destination
    || ($campaign_data['state']['active']['room_id'] ?? NULL) !== $destination
    || ($stored_dungeon['active_room_id'] ?? NULL) !== $destination) {
    throw new \RuntimeException('Fresh-campaign follower or active-state placement did not converge.');
  }
  echo "Fresh campaign {$campaign_id}: owner, familiar, and active pointers reached {$destination}; rolling back.\n";
}
finally {
  $transaction->rollBack();
}
