<?php
/**
 * @file
 * Multi-round combat cycle test.
 *
 * Validates a deeper encounter lifecycle:
 *   exploration -> dialogue-driven combat start -> player end_turn
 *   -> NPC auto-play -> round 2 -> player finishes encounter -> exploration
 *
 * Run with:
 *   drush php:script web/modules/custom/dungeoncrawler_content/tests/multi_round_combat_cycle_test.php
 */

use Drupal\dungeoncrawler_content\Service\CampaignInitializationService;
use Drupal\dungeoncrawler_content\Service\GameCoordinatorService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;

$GLOBALS['test_pass'] = 0;
$GLOBALS['test_fail'] = 0;
$GLOBALS['test_errors'] = [];

function assert_true($condition, $label) {
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

function assert_equals($expected, $actual, $label) {
  if ($expected === $actual) {
    $GLOBALS['test_pass']++;
    echo "  ✓ {$label}\n";
  }
  else {
    $GLOBALS['test_fail']++;
    $GLOBALS['test_errors'][] = "{$label} (expected: " . var_export($expected, TRUE) . ", got: " . var_export($actual, TRUE) . ")";
    echo "  ✗ FAIL: {$label} (expected: " . var_export($expected, TRUE) . ", got: " . var_export($actual, TRUE) . ")\n";
  }
}

function persist_multi_round_dungeon($db, int $campaign_id, array $dungeon_data): void {
  $db->update('dc_campaign_dungeons')
    ->fields([
      'dungeon_data' => json_encode($dungeon_data),
      'updated' => time(),
    ])
    ->condition('campaign_id', $campaign_id)
    ->execute();
}

function find_multi_round_room(array $dungeon_data, string $room_id): ?array {
  foreach (($dungeon_data['rooms'] ?? []) as $room) {
    if (($room['room_id'] ?? '') === $room_id) {
      return $room;
    }
  }
  return NULL;
}

function cleanup_multi_round_campaign($db, int $campaign_id): void {
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

function has_logged_event_type(array $events, string $type): bool {
  foreach ($events as $event) {
    if (($event['type'] ?? '') === $type) {
      return TRUE;
    }
  }
  return FALSE;
}

echo "=== Multi-Round Combat Cycle Test ===\n\n";

/** @var CampaignInitializationService $init */
$init = \Drupal::service('dungeoncrawler_content.campaign_initialization');
/** @var RoomChatService $room_chat */
$room_chat = \Drupal::service('dungeoncrawler_content.room_chat_service');
/** @var GameCoordinatorService $game_coordinator */
$game_coordinator = \Drupal::service('dungeoncrawler_content.game_coordinator');
$db = \Drupal::database();

$campaign_id = 0;

try {
  $uid = (int) \Drupal::currentUser()->id();
  if ($uid < 1) {
    $uid = 1;
  }

  $campaign_id = $init->initializeCampaign($uid, 'Multi-Round Combat Cycle Test Campaign', 'classic_dungeon', 'normal');
  assert_true($campaign_id > 0, 'Test campaign created');

  $dungeon_data_raw = $db->select('dc_campaign_dungeons', 'd')
    ->fields('d', ['dungeon_data'])
    ->condition('campaign_id', $campaign_id)
    ->orderBy('id', 'DESC')
    ->range(0, 1)
    ->execute()
    ->fetchField();
  $dungeon_data = json_decode($dungeon_data_raw ?: '{}', TRUE) ?: [];

  $room_id = (string) ($dungeon_data['active_room_id'] ?? '');
  if ($room_id === '' && !empty($dungeon_data['rooms'][0]['room_id'])) {
    $room_id = (string) $dungeon_data['rooms'][0]['room_id'];
    $dungeon_data['active_room_id'] = $room_id;
  }
  if ($room_id === '') {
    $room_id = 'multi_round_cycle_room';
    $dungeon_data['active_room_id'] = $room_id;
    $dungeon_data['rooms'][] = [
      'room_id' => $room_id,
      'name' => 'Multi Round Cycle Room',
      'description' => 'A room reserved for multi-round combat testing.',
      'hexes' => [['q' => 0, 'r' => 0], ['q' => 1, 'r' => 0]],
      'terrain' => [],
      'gameplay_state' => [],
    ];
  }
  $room_meta = find_multi_round_room($dungeon_data, $room_id);
  assert_true(is_array($room_meta), 'Test room resolved');

  $hero_id = '930001';
  $enemy_id = '930002';
  $hero_hp = 250;
  $enemy_hp = 100;

  $dungeon_data['entities'] = [
    [
      'entity_instance_id' => $hero_id,
      'entity_type' => 'player_character',
      'entity_ref' => ['content_type' => 'player_character', 'content_id' => 'multi_round_hero'],
      'name' => 'Torgar Ironforge',
      'placement' => ['room_id' => $room_id, 'hex' => ['q' => 0, 'r' => 0]],
      'state' => [
        'metadata' => [
          'display_name' => 'Torgar Ironforge',
          'team' => 'player',
          'stats' => [
            'currentHp' => $hero_hp,
            'maxHp' => $hero_hp,
            'ac' => 20,
            'perception' => 1000,
          ],
        ],
        'hit_points' => ['current' => $hero_hp, 'max' => $hero_hp],
        'armor_class' => 20,
        'perception' => 1000,
      ],
    ],
    [
      'entity_instance_id' => $enemy_id,
      'entity_type' => 'npc',
      'entity_ref' => ['content_type' => 'npc', 'content_id' => 'gribbles_multi_round'],
      'name' => 'Gribbles',
      'placement' => ['room_id' => $room_id, 'hex' => ['q' => 1, 'r' => 0]],
      'state' => [
        'metadata' => [
          'display_name' => 'Gribbles',
          'team' => 'hostile',
          'role' => 'enemy',
          'description' => 'A durable hostile target for round advancement testing.',
          'stats' => [
            'currentHp' => $enemy_hp,
            'maxHp' => $enemy_hp,
            'ac' => 10,
            'perception' => -1000,
          ],
        ],
        'hit_points' => ['current' => $enemy_hp, 'max' => $enemy_hp],
        'armor_class' => 10,
        'perception' => -1000,
      ],
    ],
  ];

  $dungeon_data['game_state']['phase'] = 'exploration';
  $dungeon_data['game_state']['encounter_id'] = NULL;
  $dungeon_data['game_state']['round'] = NULL;
  $dungeon_data['game_state']['turn'] = NULL;
  $dungeon_data['game_state']['initiative_order'] = NULL;
  persist_multi_round_dungeon($db, $campaign_id, $dungeon_data);

  echo "--- Stage 1: Narrative starts combat ---\n";

  $room_ref = new ReflectionClass($room_chat);
  $start_combat = $room_ref->getMethod('handleCombatInitiationAction');
  $start_combat->setAccessible(TRUE);
  $combat_result = $start_combat->invoke($room_chat, $campaign_id, $room_id, $room_meta, $dungeon_data, [
    'type' => 'combat_initiation',
    'name' => 'Attack Gribbles',
    'details' => [
      'combat' => [
        'reason' => 'Torgar says "I attack Gribbles".',
        'target_name' => 'Gribbles',
      ],
    ],
  ]);

  assert_true(!empty($combat_result['success']), 'Dialogue-driven combat initiation succeeds');
  $combat_state = $combat_result['transition']['game_state'] ?? [];
  assert_equals('encounter', $combat_state['phase'] ?? NULL, 'State enters encounter phase');
  assert_equals(1, (int) ($combat_state['round'] ?? 0), 'Encounter begins at round 1');
  assert_equals($hero_id, (string) ($combat_state['turn']['entity'] ?? ''), 'Hero gets the opening turn');

  $encounter_id = (int) ($combat_state['encounter_id'] ?? 0);
  assert_true($encounter_id > 0, 'Encounter id assigned');

  echo "\n--- Stage 2: Player ends turn and NPC auto-play advances round ---\n";

  $end_turn_result = $game_coordinator->processAction($campaign_id, [
    'type' => 'end_turn',
    'actor' => $hero_id,
    'target' => NULL,
    'params' => [],
  ]);

  assert_true(!empty($end_turn_result['success']), 'End turn action succeeds');
  assert_equals('encounter', $end_turn_result['game_state']['phase'] ?? NULL, 'Encounter remains active after end turn');
  assert_equals(2, (int) ($end_turn_result['game_state']['round'] ?? 0), 'Round advances to 2 after NPC turn completes');
  assert_equals($hero_id, (string) ($end_turn_result['game_state']['turn']['entity'] ?? ''), 'Turn returns to hero after NPC auto-play');
  assert_true(empty($end_turn_result['phase_transition']), 'End turn does not end the encounter prematurely');
  assert_true(has_logged_event_type($end_turn_result['events'] ?? [], 'end_turn'), 'End turn event is logged');
  assert_true(has_logged_event_type($end_turn_result['events'] ?? [], 'npc_strike'), 'NPC strike event is logged');
  assert_true(has_logged_event_type($end_turn_result['events'] ?? [], 'round_start'), 'Round start event is logged');

  $hero_row = $db->select('combat_participants', 'p')
    ->fields('p', ['hp', 'is_defeated'])
    ->condition('encounter_id', $encounter_id)
    ->condition('entity_id', $hero_id)
    ->execute()
    ->fetchAssoc();
  assert_true(!empty($hero_row), 'Hero participant row exists after NPC turn');
  assert_true((int) ($hero_row['hp'] ?? $hero_hp) < $hero_hp, 'Hero took damage during NPC auto-play');
  assert_true((int) ($hero_row['is_defeated'] ?? 0) === 0, 'Hero survives into round 2');

  $damage_rows = $db->select('combat_damage_log', 'd')
    ->fields('d', ['id'])
    ->condition('encounter_id', $encounter_id)
    ->execute()
    ->fetchCol();
  assert_true(count($damage_rows) >= 1, 'Damage log contains the NPC attack');

  echo "\n--- Stage 3: Hero finishes the fight and exploration resumes ---\n";

  $strike_result = $game_coordinator->processAction($campaign_id, [
    'type' => 'strike',
    'actor' => $hero_id,
    'target' => $enemy_id,
    'params' => [
      'attack_bonus' => 100,
      'damage_dice' => '1d8+200',
      'damage_type' => 'slashing',
    ],
  ]);

  assert_true(!empty($strike_result['success']), 'Hero strike succeeds in round 2');
  assert_true(!empty($strike_result['result']['strike']), 'Hero strike result is returned');
  assert_true(!empty($strike_result['phase_transition']), 'Hero strike ends the encounter');
  assert_equals('encounter', $strike_result['phase_transition']['from'] ?? NULL, 'Encounter transition starts from encounter');
  assert_equals('exploration', $strike_result['phase_transition']['to'] ?? NULL, 'Encounter transition returns to exploration');
  assert_equals('exploration', $strike_result['game_state']['phase'] ?? NULL, 'Game state returns to exploration after round 2 victory');
  assert_true(empty($strike_result['game_state']['encounter_id']), 'Encounter id is cleared after multi-round cycle');
  assert_true(in_array('move', $strike_result['available_actions'] ?? [], TRUE), 'Exploration move action is available again');
  assert_true(in_array('talk', $strike_result['available_actions'] ?? [], TRUE), 'Exploration talk action is available again');
}
catch (Throwable $e) {
  assert_true(FALSE, 'Unhandled exception: ' . $e->getMessage());
}
finally {
  if ($campaign_id > 0) {
    cleanup_multi_round_campaign($db, $campaign_id);
  }
}

echo "\n=== Summary ===\n";
echo 'Passed: ' . $GLOBALS['test_pass'] . "\n";
echo 'Failed: ' . $GLOBALS['test_fail'] . "\n";
if (!empty($GLOBALS['test_errors'])) {
  echo "\nFailures:\n";
  foreach ($GLOBALS['test_errors'] as $error) {
    echo " - {$error}\n";
  }
}