<?php

/**
 * Exercise the published Island quest through player-scoped room discovery.
 *
 * Run with the site-local Drush and a 2 GB PHP limit; all trial writes roll back.
 */

$database = \Drupal::database();
$release_id = '04764018-1b96-41a8-a5ad-99ec66220a0c';
$source = \Drupal::service('dungeoncrawler_content.module_conversion.release')->resolveCampaignSource($release_id);
if (($source['release_id'] ?? NULL) !== $release_id) {
  throw new \RuntimeException('Island survey release must be published and launchable.');
}

$transaction = $database->startTransaction();
try {
  $campaign_id = \Drupal::service('dungeoncrawler_content.campaign_initialization')->initializeCampaign(
    1, 'Island discovery audit', 'island_exploration', 'normal',
    ['kind' => 'module_release', 'release_id' => $release_id]
  );
  $quest = $database->select('dc_campaign_quests', 'q')
    ->fields('q', ['quest_id', 'generated_objectives'])
    ->condition('campaign_id', $campaign_id)
    ->condition('source_template_id', 'island-survey-v1')
    ->execute()->fetchAssoc();
  if (!is_array($quest)) {
    throw new \RuntimeException('The published survey quest was not offered.');
  }
  $objectives = json_decode((string) $quest['generated_objectives'], TRUE, 512, JSON_THROW_ON_ERROR)[0]['objectives'] ?? [];
  if (count($objectives) !== 10) {
    throw new \RuntimeException('The survey must have ten placement-bound discovery objectives.');
  }
  $character_id = (int) $database->insert('dc_campaign_characters')
    ->fields([
      'campaign_id' => $campaign_id,
      'character_id' => 0,
      'uid' => 1,
      'name' => 'Island discovery audit player',
      'type' => 'pc',
      'role' => 'player',
      'is_active' => 1,
    ])->execute();
  $runtime_dungeon_id = (string) $database->select('dc_campaign_dungeons', 'd')
    ->fields('d', ['dungeon_id'])
    ->condition('campaign_id', $campaign_id)
    ->range(0, 1)->execute()->fetchField();
  $state_service = \Drupal::service('dungeoncrawler_content.room_state_service');
  $entrance_id = (string) ($objectives[0]['location_id'] ?? '');
  $visited_before_acceptance = (string) ($objectives[1]['location_id'] ?? '');
  $dungeon_json = $database->select('dc_campaign_dungeons', 'd')
    ->fields('d', ['dungeon_data'])
    ->condition('campaign_id', $campaign_id)
    ->condition('dungeon_id', $runtime_dungeon_id)->execute()->fetchField();
  $dungeon_data = json_decode((string) $dungeon_json, TRUE, 512, JSON_THROW_ON_ERROR);
  $navigation_service = \Drupal::service('dungeoncrawler_content.navigation_service');
  $exploration_handler = \Drupal::service('dungeoncrawler_content.exploration_phase_handler');
  foreach ($objectives as $objective) {
    $destination = (string) ($objective['location_id'] ?? '');
    $route = $navigation_service->resolveRoomRoutePlan($dungeon_data, $entrance_id, $destination);
    if (!is_array($route) || ($route['path_room_ids'][0] ?? NULL) !== $entrance_id
      || end($route['path_room_ids']) !== $destination) {
      throw new \RuntimeException("No navigable room route reaches quest destination {$destination}.");
    }
    foreach (array_slice($route['path_room_ids'], 1) as $hop => $next_room) {
      $from_room = $route['path_room_ids'][$hop];
      $dungeon_data['active_room_id'] = $from_room;
      $exits = $navigation_service->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $from_room);
      $capability = NULL;
      foreach ($exits as $exit) {
        if (($exit['target_room_id'] ?? NULL) === $next_room) {
          $capability = $exit;
          break;
        }
      }
      if (!is_array($capability) || empty($capability['available']) || empty($capability['is_discovered'])) {
        throw new \RuntimeException("The Action Rail has no available exit from {$from_room} to {$next_room}.");
      }
      $intent = [
        'type' => 'transition',
        'params' => [
          'target_room_id' => $next_room,
          'connection_id' => (string) $capability['connection_id'],
          'target_hex' => $capability['origin_hex'],
        ],
      ];
      if (empty($exploration_handler->validateIntent($intent, [], $dungeon_data)['valid'])) {
        throw new \RuntimeException("A player transition from {$from_room} to {$next_room} was rejected.");
      }
    }
  }
  $dungeon_data['active_room_id'] = $entrance_id;
  $entrance_exits = $navigation_service->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $entrance_id);
  $forged_intent = [
    'type' => 'transition',
    'params' => [
      'target_room_id' => (string) end($objectives)['location_id'],
      'connection_id' => (string) ($entrance_exits[0]['connection_id'] ?? ''),
    ],
  ];
  if (!empty($exploration_handler->validateIntent($forged_intent, [], $dungeon_data)['valid'])) {
    throw new \RuntimeException('An off-route player transition bypassed the published navigation graph.');
  }
  $navigation = \Drupal::service('dungeoncrawler_content.map_generator')->generateSetting(
    $campaign_id, $visited_before_acceptance, $entrance_id
  );
  if (($navigation['room']['room_id'] ?? NULL) !== $visited_before_acceptance
    || ($navigation['source'] ?? NULL) !== 'existing_campaign_room'
    || !$state_service->isDiscovered($campaign_id, $visited_before_acceptance)) {
    throw new \RuntimeException('Navigation did not reuse and discover the pinned next room.');
  }
  if ($character_id <= 0 || $runtime_dungeon_id === ''
    || !\Drupal::service('dungeoncrawler_content.storyline_quest_lifecycle')
      ->startOfferedQuest($campaign_id, (string) $quest['quest_id'], $character_id)) {
    throw new \RuntimeException('The player-scoped survey quest did not activate.');
  }

  if (!$state_service->isDiscovered($campaign_id, $entrance_id)) {
    throw new \RuntimeException('The active entrance must be discovered at campaign launch.');
  }
  $initial_progress = $database->select('dc_campaign_quest_progress', 'p')
    ->fields('p', ['objective_states'])
    ->condition('campaign_id', $campaign_id)
    ->condition('quest_id', $quest['quest_id'])
    ->condition('character_id', $character_id)->execute()->fetchField();
  $initial_objectives = json_decode((string) $initial_progress, TRUE, 512, JSON_THROW_ON_ERROR)[0]['objectives'] ?? [];
  if (count(array_filter($initial_objectives, static fn(array $objective): bool => !empty($objective['completed']))) !== 2
    || empty($initial_objectives[0]['completed']) || empty($initial_objectives[1]['completed'])) {
    throw new \RuntimeException('Quest acceptance must credit exactly the two already-discovered rooms.');
  }
  foreach ($objectives as $index => $objective) {
    $room_id = (string) ($objective['location_id'] ?? '');
    if ($index < 2) {
      continue;
    }
    $before = $database->select('dc_campaign_room_states', 's')
      ->fields('s', ['fog_state'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)->execute()->fetchField();
    $previous = json_decode((string) $before, TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($previous) || !empty($previous['explored'])) {
      throw new \RuntimeException("Room {$room_id} must start undiscovered despite its precreated state row.");
    }
    $arrival = [
      'roomId' => $room_id,
      'dungeonId' => $runtime_dungeon_id,
      'explored' => TRUE,
      'visibility' => 'visible',
      'isCleared' => FALSE,
    ];
    if ($index === count($objectives) - 1) {
      $rewards = (string) $database->select('dc_campaign_quests', 'q')
        ->fields('q', ['generated_rewards'])
        ->condition('campaign_id', $campaign_id)
        ->condition('quest_id', $quest['quest_id'])->execute()->fetchField();
      $database->update('dc_campaign_quests')
        ->fields(['generated_rewards' => '{"xp":100,"items":[]}'])
        ->condition('campaign_id', $campaign_id)
        ->condition('quest_id', $quest['quest_id'])->execute();
      try {
        $state_service->setState($campaign_id, $room_id, $runtime_dungeon_id, $arrival, NULL);
        throw new \RuntimeException('Invalid quest rewards unexpectedly passed discovery completion.');
      }
      catch (\RuntimeException $e) {
        if (!str_contains($e->getMessage(), 'missing required key "gold"')) {
          throw $e;
        }
      }
      if ($state_service->isDiscovered($campaign_id, $room_id)) {
        throw new \RuntimeException('Failed quest completion must roll back the room discovery.');
      }
      $database->update('dc_campaign_quests')
        ->fields(['generated_rewards' => $rewards])
        ->condition('campaign_id', $campaign_id)
        ->condition('quest_id', $quest['quest_id'])->execute();
    }
    $state_service->setState($campaign_id, $room_id, $runtime_dungeon_id, $arrival, NULL);
    $progress_json = $database->select('dc_campaign_quest_progress', 'p')
      ->fields('p', ['objective_states'])
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $quest['quest_id'])
      ->condition('character_id', $character_id)->execute()->fetchField();
    $states = json_decode((string) $progress_json, TRUE, 512, JSON_THROW_ON_ERROR)[0]['objectives'] ?? [];
    $completed = array_values(array_filter($states, static fn(array $state): bool => !empty($state['completed'])));
    if (count($completed) !== $index + 1 || empty($states[$index]['completed'])) {
      throw new \RuntimeException("Exploring {$room_id} did not advance exactly its own objective.");
    }
  }
  $status = $database->select('dc_campaign_quests', 'q')
    ->fields('q', ['status'])
    ->condition('campaign_id', $campaign_id)
    ->condition('quest_id', $quest['quest_id'])->execute()->fetchField();
  if ($status !== 'completed') {
    throw new \RuntimeException('Discovering all ten survey rooms did not complete the quest.');
  }
}
finally {
  $transaction->rollBack();
}
if ((int) $database->select('dc_campaign_rooms', 'r')->condition('campaign_id', $campaign_id)->countQuery()->execute()->fetchField() !== 0) {
  throw new \RuntimeException('The discovery audit left campaign rooms behind.');
}
echo "Island player exits authorize every route hop and reject off-route travel; ten discovery objectives completed; trial rolled back.\n";
