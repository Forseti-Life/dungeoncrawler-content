<?php

/**
 * Exercise the Island survey through the player action coordinator.
 *
 * Run with the site-local Drush and a 2 GB PHP limit; all trial writes roll back.
 */

$database = \Drupal::database();
$release_id = '04764018-1b96-41a8-a5ad-99ec66220a0c';
$transaction = $database->startTransaction();
try {
  $campaign_id = \Drupal::service('dungeoncrawler_content.campaign_initialization')->initializeCampaign(
    1, 'Island player travel audit', 'island_exploration', 'normal',
    ['kind' => 'module_release', 'release_id' => $release_id]
  );
  $dungeon_row = $database->select('dc_campaign_dungeons', 'd')
    ->fields('d', ['dungeon_id', 'dungeon_data'])
    ->condition('d.campaign_id', $campaign_id)->range(0, 1)->execute()->fetchAssoc();
  if (!is_array($dungeon_row)) {
    throw new \RuntimeException('Published Island runtime dungeon was not created.');
  }
  $dungeon = json_decode((string) $dungeon_row['dungeon_data'], TRUE, 512, JSON_THROW_ON_ERROR);
  $entrance = (string) ($dungeon['active_room_id'] ?? '');
  $source_player = $database->select('dc_campaign_characters', 'c')
    ->fields('c')
    ->condition('c.uid', 1)
    ->condition('c.role', 'player')
    ->condition('c.source_character_id', 0, '>')
    ->orderBy('c.id', 'DESC')
    ->range(0, 1)->execute()->fetchAssoc();
  if (!is_array($source_player)) {
    throw new \RuntimeException('The owner has no canonical player character available for the rollback trial.');
  }
  unset($source_player['id']);
  $source_player['campaign_id'] = $campaign_id;
  $source_player['instance_id'] = \Drupal::service('uuid')->generate();
  $source_player['uuid'] = \Drupal::service('uuid')->generate();
  $source_player['location_type'] = 'room';
  $source_player['location_ref'] = $entrance;
  $source_player['last_room_id'] = $entrance;
  $source_player['name'] = 'Island audit player';
  $source_player['is_active'] = 1;
  $character_id = (int) $database->insert('dc_campaign_characters')->fields($source_player)->execute();
  $quest = $database->select('dc_campaign_quests', 'q')
    ->fields('q', ['quest_id', 'generated_objectives'])
    ->condition('q.campaign_id', $campaign_id)
    ->condition('q.source_template_id', 'island-survey-v1')
    ->execute()->fetchAssoc();
  if (!is_array($quest)) {
    throw new \RuntimeException('Island survey quest was not offered.');
  }
  $objectives = json_decode((string) $quest['generated_objectives'], TRUE, 512, JSON_THROW_ON_ERROR)[0]['objectives'] ?? [];
  if (count($objectives) !== 10 || !\Drupal::service('dungeoncrawler_content.storyline_quest_lifecycle')
    ->startOfferedQuest($campaign_id, (string) $quest['quest_id'], $character_id)) {
    throw new \RuntimeException('The trial player could not accept the ten-room survey.');
  }

  $coordinator = \Drupal::service('dungeoncrawler_content.game_coordinator');
  (new \ReflectionProperty($coordinator, 'narrationEngine'))->setValue($coordinator, NULL);
  $navigation = \Drupal::service('dungeoncrawler_content.navigation_service');
  $room_states = \Drupal::service('dungeoncrawler_content.room_state_service');
  $entrance_marker = NULL;
  $other_marker = NULL;
  foreach ($dungeon['rooms'] as $room) {
    if (($room['room_id'] ?? '') === $entrance) {
      $entrance_marker = $room['entities'][0]['instance_id'] ?? NULL;
    }
    else {
      $other_marker ??= $room['entities'][0]['instance_id'] ?? NULL;
    }
  }
  if (!is_string($entrance_marker) || !is_string($other_marker)) {
    throw new \RuntimeException('Island interaction audit requires markers in two different rooms.');
  }
  $marker_item_id = NULL;
  foreach ($dungeon['rooms'] as $room) {
    if (($room['room_id'] ?? '') === $entrance) {
      $marker_item_id = $room['entities'][0]['definition_ref']['definition_id'] ?? NULL;
      break;
    }
  }
  if (!is_string($marker_item_id) || $marker_item_id === '') {
    throw new \RuntimeException('Island entrance marker has no canonical item definition.');
  }
  $progress_json = $database->select('dc_campaign_quest_progress', 'p')
    ->fields('p', ['objective_states'])
    ->condition('campaign_id', $campaign_id)
    ->condition('quest_id', $quest['quest_id'])
    ->condition('character_id', $character_id)->execute()->fetchField();
  $trial_states = json_decode((string) $progress_json, TRUE, 512, JSON_THROW_ON_ERROR);
  $interaction_objective = [
    'objective_id' => 'synthetic-marker-interaction',
    'type' => 'interact',
    'target' => $marker_item_id,
    'item' => $marker_item_id,
    'location' => $entrance,
    'location_id' => $entrance,
    'description' => 'Inspect the original survey marker.',
    'completed' => FALSE,
    'completion_criteria' => ['kind' => 'flag', 'metric' => 'completed', 'required_value' => TRUE],
  ];
  $trial_states[0]['objectives'][] = $interaction_objective;
  $database->update('dc_campaign_quest_progress')
    ->fields(['objective_states' => json_encode($trial_states, JSON_THROW_ON_ERROR)])
    ->condition('campaign_id', $campaign_id)
    ->condition('quest_id', $quest['quest_id'])
    ->condition('character_id', $character_id)->execute();
  $trial_generated = json_decode((string) $quest['generated_objectives'], TRUE, 512, JSON_THROW_ON_ERROR);
  $trial_generated[0]['objectives'][] = $interaction_objective;
  $database->update('dc_campaign_quests')
    ->fields(['generated_objectives' => json_encode($trial_generated, JSON_THROW_ON_ERROR)])
    ->condition('campaign_id', $campaign_id)
    ->condition('quest_id', $quest['quest_id'])->execute();
  foreach ([$other_marker, 'missing-marker'] as $invalid_marker) {
    $invalid = $coordinator->processAction($campaign_id, [
      'type' => 'interact',
      'actor' => $source_player['instance_id'],
      'target' => $invalid_marker,
    ]);
    if (!empty($invalid['success'])) {
      throw new \RuntimeException('Player interaction accepted an off-room or absent marker.');
    }
  }
  $valid_rewards = (string) $database->select('dc_campaign_quests', 'q')
    ->fields('q', ['generated_rewards'])
    ->condition('campaign_id', $campaign_id)
    ->condition('quest_id', $quest['quest_id'])->execute()->fetchField();
  $failed_states = $trial_states;
  foreach ($failed_states[0]['objectives'] as &$objective) {
    if (($objective['type'] ?? NULL) === 'explore') {
      $objective['completed'] = TRUE;
      $objective['discovered'] = TRUE;
    }
  }
  unset($objective);
  $database->update('dc_campaign_quest_progress')
    ->fields(['objective_states' => json_encode($failed_states, JSON_THROW_ON_ERROR)])
    ->condition('campaign_id', $campaign_id)
    ->condition('quest_id', $quest['quest_id'])
    ->condition('character_id', $character_id)->execute();
  $database->update('dc_campaign_quests')
    ->fields(['generated_rewards' => json_encode(['xp' => 100], JSON_THROW_ON_ERROR)])
    ->condition('campaign_id', $campaign_id)
    ->condition('quest_id', $quest['quest_id'])->execute();
  try {
    $coordinator->processAction($campaign_id, [
      'type' => 'interact',
      'actor' => $source_player['instance_id'],
      'target' => $entrance_marker,
    ]);
    throw new \RuntimeException('Malformed quest rewards unexpectedly allowed the marker interaction.');
  }
  catch (\RuntimeException $e) {
    if (!str_contains($e->getMessage(), 'missing required key "gold"')) {
      throw $e;
    }
  }
  $persisted_after_failure = (string) $database->select('dc_campaign_quest_progress', 'p')
    ->fields('p', ['objective_states'])
    ->condition('campaign_id', $campaign_id)
    ->condition('quest_id', $quest['quest_id'])
    ->condition('character_id', $character_id)->execute()->fetchField();
  if (json_decode($persisted_after_failure, TRUE, 512, JSON_THROW_ON_ERROR) !== $failed_states) {
    throw new \RuntimeException('Failed interaction did not roll back its quest progress.');
  }
  $database->update('dc_campaign_quest_progress')
    ->fields(['objective_states' => json_encode($trial_states, JSON_THROW_ON_ERROR)])
    ->condition('campaign_id', $campaign_id)
    ->condition('quest_id', $quest['quest_id'])
    ->condition('character_id', $character_id)->execute();
  $database->update('dc_campaign_quests')
    ->fields(['generated_rewards' => $valid_rewards])
    ->condition('campaign_id', $campaign_id)
    ->condition('quest_id', $quest['quest_id'])->execute();
  $interaction = $coordinator->processAction($campaign_id, [
    'type' => 'interact',
    'actor' => $source_player['instance_id'],
    'target' => $entrance_marker,
  ]);
  if (empty($interaction['success']) || empty($interaction['result']['quest_touchpoint']['success'])
    || ($interaction['result']['quest_touchpoint']['decision'] ?? '') !== 'APPLY_PROGRESS'
    || ($interaction['result']['quest_touchpoint']['objective_id'] ?? '') !== 'synthetic-marker-interaction') {
    throw new \RuntimeException('Placed survey marker interaction did not traverse the canonical quest touchpoint path: ' . json_encode($interaction['error'] ?? $interaction['result'] ?? NULL));
  }
  $first_destination = (string) $objectives[1]['location_id'];
  $trial_notes = [['author' => 'Island audit player', 'text' => 'Island survey trial note']];
  $room_states->setState($campaign_id, $first_destination, (string) $dungeon_row['dungeon_id'], [
    'roomId' => $first_destination,
    'dungeonId' => (string) $dungeon_row['dungeon_id'],
    'explored' => FALSE,
    'visibility' => 'fog_of_war',
    'notes' => $trial_notes,
  ], NULL);
  $last_destination = (string) end($objectives)['location_id'];
  $entrance_exits = $navigation->buildNavigationCapabilitiesWithRoadNetwork($dungeon, $entrance);
  $forged = $coordinator->processAction($campaign_id, [
    'type' => 'transition',
    'actor' => $source_player['instance_id'],
    'params' => [
      'character_id' => $character_id,
      'target_room_id' => $last_destination,
      'connection_id' => (string) ($entrance_exits[0]['connection_id'] ?? ''),
    ],
  ]);
  if (!empty($forged['success']) || $room_states->isDiscovered($campaign_id, $last_destination)) {
    throw new \RuntimeException('The player coordinator accepted an off-route Island transition.');
  }
  $current = $entrance;
  $transition_count = 0;
  foreach (array_slice($objectives, 1) as $index => $objective) {
    $destination = (string) $objective['location_id'];
    $route = $navigation->resolveRoomRoutePlan($dungeon, $current, $destination);
    if (!is_array($route)) {
      throw new \RuntimeException("No route from {$current} to {$destination} for the player.");
    }
    foreach (array_slice($route['path_room_ids'], 1) as $next) {
      $dungeon['active_room_id'] = $current;
      $exits = $navigation->buildNavigationCapabilitiesWithRoadNetwork($dungeon, $current);
      $exit = array_values(array_filter($exits, static fn(array $capability): bool => ($capability['target_room_id'] ?? '') === $next))[0] ?? NULL;
      if (!is_array($exit) || empty($exit['available']) || empty($exit['is_discovered'])) {
        throw new \RuntimeException("The player cannot select the exit from {$current} to {$next}.");
      }
      $intent = [
        'type' => 'transition',
        'actor' => $source_player['instance_id'],
        'params' => [
          'character_id' => $character_id,
          'target_room_id' => $next,
          'connection_id' => (string) $exit['connection_id'],
          'target_hex' => $exit['origin_hex'],
        ],
      ];
      if ($index === count($objectives) - 2 && $next === $destination) {
        $valid_rewards = (string) $database->select('dc_campaign_quests', 'q')
          ->fields('q', ['generated_rewards'])
          ->condition('campaign_id', $campaign_id)
          ->condition('quest_id', $quest['quest_id'])->execute()->fetchField();
        $database->update('dc_campaign_quests')
          ->fields(['generated_rewards' => '{"xp":100,"items":[]}'])
          ->condition('campaign_id', $campaign_id)
          ->condition('quest_id', $quest['quest_id'])->execute();
        try {
          $coordinator->processAction($campaign_id, $intent);
          throw new \RuntimeException('Malformed quest rewards unexpectedly allowed the final player transition.');
        }
        catch (\RuntimeException $e) {
          if (!str_contains($e->getMessage(), 'missing required key "gold"')) {
            throw $e;
          }
        }
        $persisted_room_id = (string) $database->select('dc_campaign_runtime_state', 's')
          ->fields('s', ['active_room_id'])
          ->condition('campaign_id', $campaign_id)
          ->execute()->fetchField();
        $discovered_after_failure = $room_states->isDiscovered($campaign_id, $next);
        if ($persisted_room_id !== $current || $discovered_after_failure) {
          throw new \RuntimeException(sprintf(
            'Failed player transition rollback mismatch: expected room %s, persisted room %s, discovered=%s.',
            $current, $persisted_room_id, $discovered_after_failure ? 'yes' : 'no'
          ));
        }
        $database->update('dc_campaign_quests')
          ->fields(['generated_rewards' => $valid_rewards])
          ->condition('campaign_id', $campaign_id)
          ->condition('quest_id', $quest['quest_id'])->execute();
      }
      $response = $coordinator->processAction($campaign_id, $intent);
      if (empty($response['success']) || ($response['active_room_id'] ?? '') !== $next
        || !$room_states->isDiscovered($campaign_id, $next)) {
        throw new \RuntimeException("Player transition into {$next} failed to discover the destination: " . json_encode($response['error'] ?? NULL));
      }
      $current = $next;
      $transition_count++;
      if ($next === $first_destination
        && ($room_states->getState($campaign_id, $next)['state']['notes'] ?? NULL) !== $trial_notes) {
        throw new \RuntimeException('Player arrival discarded existing room progress notes.');
      }
    }
    if (!$room_states->isDiscovered($campaign_id, $destination)) {
      throw new \RuntimeException("Survey destination {$destination} was not discovered.");
    }
  }
  $progress = $database->select('dc_campaign_quest_progress', 'p')
    ->fields('p', ['objective_states'])
    ->condition('p.campaign_id', $campaign_id)
    ->condition('p.quest_id', $quest['quest_id'])
    ->condition('p.character_id', $character_id)->execute()->fetchField();
  $states = json_decode((string) $progress, TRUE, 512, JSON_THROW_ON_ERROR)[0]['objectives'] ?? [];
  if (count($states) !== 11
    || count(array_filter($states, static fn(array $state): bool => !empty($state['completed']))) !== 11
    || $database->select('dc_campaign_quests', 'q')->fields('q', ['status'])
      ->condition('campaign_id', $campaign_id)->condition('quest_id', $quest['quest_id'])
      ->execute()->fetchField() !== 'completed') {
    throw new \RuntimeException('Player-controlled actions did not complete the ten survey objectives and synthetic marker interaction.');
  }
}
finally {
  $transaction->rollBack();
}
foreach (['dc_campaigns' => 'id', 'dc_campaign_characters' => 'campaign_id', 'dc_campaign_room_states' => 'campaign_id', 'dc_campaign_runtime_state' => 'campaign_id'] as $table => $column) {
  if ((int) $database->select($table, 't')
    ->condition("t.{$column}", $campaign_id)
    ->countQuery()->execute()->fetchField() !== 0) {
    throw new \RuntimeException("The player transition audit left records in {$table}.");
  }
}
echo "Island player actions traversed {$transition_count} exits, completed ten objectives, and rolled back/retried a failed final transition; trial rolled back.\n";
