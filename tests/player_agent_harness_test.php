<?php
/**
 * @file
 * Bootstrap integration test for the player-agent harness.
 *
 * Validates a deterministic exploration loop:
 *   search current room -> talk to an NPC -> transition to a connected room
 *
 * Run with:
 *   drush php:script web/modules/custom/dungeoncrawler_content/tests/player_agent_harness_test.php
 */

use Drupal\dungeoncrawler_content\Service\CampaignInitializationService;
use Drupal\dungeoncrawler_content\Service\PlayerAgentHarnessService;

$GLOBALS['test_pass'] = 0;
$GLOBALS['test_fail'] = 0;
$GLOBALS['test_errors'] = [];

function assert_player_agent(bool $condition, string $label): void {
  if ($condition) {
    $GLOBALS['test_pass']++;
    echo "  ✓ {$label}\n";
  }
  else {
    $GLOBALS['test_fail']++;
    $GLOBALS['test_errors'][] = $label;
    echo "  ✗ FAIL: {$label}\n";
  }
}

function cleanup_player_agent_campaign($db, int $campaign_id): void {
  $encounter_ids = $db->select('combat_encounters', 'e')
    ->fields('e', ['id'])
    ->condition('campaign_id', $campaign_id)
    ->execute()
    ->fetchCol();
  if (!empty($encounter_ids)) {
    $db->delete('combat_actions')->condition('encounter_id', $encounter_ids, 'IN')->execute();
    $db->delete('combat_damage_log')->condition('encounter_id', $encounter_ids, 'IN')->execute();
    $db->delete('combat_conditions')->condition('encounter_id', $encounter_ids, 'IN')->execute();
    $db->delete('combat_participants')->condition('encounter_id', $encounter_ids, 'IN')->execute();
    $db->delete('combat_encounters')->condition('id', $encounter_ids, 'IN')->execute();
  }

  $session_ids = $db->select('dc_chat_sessions', 's')
    ->fields('s', ['id'])
    ->condition('campaign_id', $campaign_id)
    ->execute()
    ->fetchCol();
  if (!empty($session_ids)) {
    $db->delete('dc_chat_messages')->condition('session_id', $session_ids, 'IN')->execute();
    $db->delete('dc_chat_sessions')->condition('id', $session_ids, 'IN')->execute();
  }

  $db->delete('dc_ai_sessions')->condition('campaign_id', $campaign_id)->execute();
  $db->delete('dc_campaign_characters')->condition('campaign_id', $campaign_id)->execute();
  $db->delete('dc_campaign_rooms')->condition('campaign_id', $campaign_id)->execute();
  $db->delete('dc_campaign_dungeons')->condition('campaign_id', $campaign_id)->execute();
  $db->delete('dc_campaigns')->condition('id', $campaign_id)->execute();
}

echo "=== Player Agent Harness Test ===\n\n";

/** @var CampaignInitializationService $init */
$init = \Drupal::service('dungeoncrawler_content.campaign_initialization');
/** @var PlayerAgentHarnessService $harness */
$harness = \Drupal::service('dungeoncrawler_content.player_agent_harness');
$db = \Drupal::database();

$campaign_id = 0;

try {
  $uid = (int) \Drupal::currentUser()->id();
  if ($uid < 1) {
    $uid = 1;
  }

  $campaign_id = $init->initializeCampaign($uid, 'Player Agent Harness Test Campaign', 'classic_dungeon', 'normal');
  assert_player_agent($campaign_id > 0, 'Test campaign created');

  $dungeon_data_raw = $db->select('dc_campaign_dungeons', 'd')
    ->fields('d', ['dungeon_data'])
    ->condition('campaign_id', $campaign_id)
    ->orderBy('id', 'DESC')
    ->range(0, 1)
    ->execute()
    ->fetchField();
  $dungeon_data = json_decode($dungeon_data_raw ?: '{}', TRUE) ?: [];

  $room_a = 'player_agent_room_a';
  $room_b = 'player_agent_room_b';
  $hero_id = '940001';
  $npc_id = '940002';

  $dungeon_data['active_room_id'] = $room_a;
  $dungeon_data['rooms'] = [
    [
      'room_id' => $room_a,
      'name' => 'Agent Test Antechamber',
      'description' => 'A quiet stone room containing one curious local.',
      'hexes' => [['q' => 0, 'r' => 0], ['q' => 1, 'r' => 0]],
      'terrain' => [],
      'gameplay_state' => [],
    ],
    [
      'room_id' => $room_b,
      'name' => 'Agent Test Hall',
      'description' => 'A connected hallway worth exploring next.',
      'hexes' => [['q' => 0, 'r' => 0], ['q' => 1, 'r' => 0]],
      'terrain' => [],
      'gameplay_state' => [],
    ],
  ];
  $dungeon_data['connections'] = [
    [
      'from' => ['room_id' => $room_a],
      'to' => ['room_id' => $room_b],
      'is_passable' => TRUE,
    ],
  ];
  $dungeon_data['entities'] = [
    [
      'entity_instance_id' => $hero_id,
      'entity_type' => 'player_character',
      'entity_ref' => ['content_type' => 'player_character', 'content_id' => 'player_agent_hero'],
      'name' => 'Aldren Vale',
      'placement' => ['room_id' => $room_a, 'hex' => ['q' => 0, 'r' => 0]],
      'state' => [
        'metadata' => [
          'display_name' => 'Aldren Vale',
          'team' => 'player',
        ],
      ],
    ],
    [
      'entity_instance_id' => $npc_id,
      'entity_type' => 'npc',
      'entity_ref' => ['content_type' => 'npc', 'content_id' => 'player_agent_npc'],
      'name' => 'Marta',
      'placement' => ['room_id' => $room_a, 'hex' => ['q' => 1, 'r' => 0]],
      'state' => [
        'metadata' => [
          'display_name' => 'Marta',
          'team' => 'friendly',
        ],
      ],
    ],
  ];

  $dungeon_data['game_state']['phase'] = 'exploration';
  $dungeon_data['game_state']['encounter_id'] = NULL;
  $dungeon_data['game_state']['round'] = NULL;
  $dungeon_data['game_state']['turn'] = NULL;
  $dungeon_data['game_state']['initiative_order'] = NULL;

  $db->update('dc_campaign_dungeons')
    ->fields([
      'dungeon_data' => json_encode($dungeon_data),
      'updated' => time(),
    ])
    ->condition('campaign_id', $campaign_id)
    ->execute();

  $profile = [
    'actor_id' => $hero_id,
    'character_name' => 'Aldren Vale',
    'persona' => ['tone' => 'curious'],
  ];

  $run = $harness->runSteps($campaign_id, $profile, 3, [], TRUE);
  assert_player_agent(!empty($run['success']), 'Harness run succeeds');
  assert_player_agent(count($run['results'] ?? []) === 3, 'Harness executes three exploration steps');

  $results = $run['results'] ?? [];
  $first_decision = $results[0]['decision']['intent']['type'] ?? NULL;
  $second_decision = $results[1]['decision']['intent']['type'] ?? NULL;
  $third_decision = $results[2]['decision']['intent']['type'] ?? NULL;

  assert_player_agent($first_decision === 'search', 'Agent searches the room first');
  assert_player_agent($second_decision === 'talk', 'Agent talks to the room NPC second');
  assert_player_agent($third_decision === 'transition', 'Agent transitions to the connected room third');

  $run_state = $run['run_state'] ?? [];
  assert_player_agent(in_array($room_a, $run_state['memory']['searched_rooms'] ?? [], TRUE), 'Run state records searched room');
  assert_player_agent(in_array($npc_id, $run_state['memory']['talked_entities'] ?? [], TRUE), 'Run state records talked NPC');
  assert_player_agent(in_array($room_b, $run_state['memory']['visited_rooms'] ?? [], TRUE), 'Run state records newly visited room');
  assert_player_agent(($run_state['progress']['visited_room_count'] ?? 0) >= 2, 'Progress tracks visited room count');

  $latest_dungeon_raw = $db->select('dc_campaign_dungeons', 'd')
    ->fields('d', ['dungeon_data'])
    ->condition('campaign_id', $campaign_id)
    ->orderBy('id', 'DESC')
    ->range(0, 1)
    ->execute()
    ->fetchField();
  $latest_dungeon = json_decode($latest_dungeon_raw ?: '{}', TRUE) ?: [];
  assert_player_agent(($latest_dungeon['active_room_id'] ?? NULL) === $room_b, 'Campaign active room moves to the connected destination');
}
catch (\Throwable $t) {
  $GLOBALS['test_fail']++;
  $GLOBALS['test_errors'][] = $t->getMessage();
  echo "  ✗ FAIL: Uncaught exception: {$t->getMessage()}\n";
}
finally {
  if ($campaign_id > 0) {
    cleanup_player_agent_campaign($db, $campaign_id);
  }
}

echo "\n=== Summary ===\n";
echo "Passed: {$GLOBALS['test_pass']}\n";
echo "Failed: {$GLOBALS['test_fail']}\n";

if ($GLOBALS['test_fail'] > 0) {
  exit(1);
}
