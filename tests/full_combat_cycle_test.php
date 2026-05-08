<?php
/**
 * @file
 * Full combat cycle test.
 *
 * Validates a complete loop:
 *   exploration -> dialogue-driven combat start -> player strike -> encounter end -> exploration
 *
 * Run with:
 *   drush php:script web/modules/custom/dungeoncrawler_content/tests/full_combat_cycle_test.php
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

function persist_cycle_dungeon($db, int $campaign_id, array $dungeon_data): void {
  $db->update('dc_campaign_dungeons')
    ->fields([
      'dungeon_data' => json_encode($dungeon_data),
      'updated' => time(),
    ])
    ->condition('campaign_id', $campaign_id)
    ->execute();
}

function find_cycle_room(array $dungeon_data, string $room_id): ?array {
  foreach (($dungeon_data['rooms'] ?? []) as $room) {
    if (($room['room_id'] ?? '') === $room_id) {
      return $room;
    }
  }
  return NULL;
}

function cleanup_cycle_campaign($db, int $campaign_id): void {
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

echo "=== Full Combat Cycle Test ===\n\n";

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

  $campaign_id = $init->initializeCampaign($uid, 'Full Combat Cycle Test Campaign', 'classic_dungeon', 'normal');
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
    $room_id = 'full_cycle_room';
    $dungeon_data['active_room_id'] = $room_id;
    $dungeon_data['rooms'][] = [
      'room_id' => $room_id,
      'name' => 'Full Cycle Room',
      'description' => 'A room reserved for full combat cycle testing.',
      'hexes' => [['q' => 0, 'r' => 0], ['q' => 1, 'r' => 0]],
      'terrain' => [],
      'gameplay_state' => [],
    ];
  }
  $room_meta = find_cycle_room($dungeon_data, $room_id);
  assert_true(is_array($room_meta), 'Test room resolved');

  $hero_id = '920001';
  $enemy_id = '920002';

  $dungeon_data['entities'] = [
    [
      'entity_instance_id' => $hero_id,
      'entity_type' => 'player_character',
      'entity_ref' => ['content_type' => 'player_character', 'content_id' => 'full_cycle_hero'],
      'name' => 'Torgar Ironforge',
      'placement' => ['room_id' => $room_id, 'hex' => ['q' => 0, 'r' => 0]],
      'state' => [
        'metadata' => [
          'display_name' => 'Torgar Ironforge',
          'team' => 'player',
          'stats' => [
            'currentHp' => 30,
            'maxHp' => 30,
            'ac' => 20,
            'perception' => 1000,
          ],
        ],
        'hit_points' => ['current' => 30, 'max' => 30],
        'armor_class' => 20,
        'perception' => 1000,
      ],
    ],
    [
      'entity_instance_id' => $enemy_id,
      'entity_type' => 'npc',
      'entity_ref' => ['content_type' => 'npc', 'content_id' => 'gribbles_rindsworth'],
      'name' => 'Gribbles',
      'placement' => ['room_id' => $room_id, 'hex' => ['q' => 1, 'r' => 0]],
      'state' => [
        'metadata' => [
          'display_name' => 'Gribbles',
          'team' => 'hostile',
          'role' => 'enemy',
          'description' => 'A hostile test target.',
          'stats' => [
            'currentHp' => 6,
            'maxHp' => 6,
            'ac' => 10,
            'perception' => -1000,
          ],
        ],
        'hit_points' => ['current' => 6, 'max' => 6],
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
  persist_cycle_dungeon($db, $campaign_id, $dungeon_data);

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

  echo "\n--- Stage 2: Player strike resolves combat ---\n";

  $strike_result = $game_coordinator->processAction($campaign_id, [
    'type' => 'strike',
    'actor' => $hero_id,
    'target' => $enemy_id,
    'params' => [
      'attack_bonus' => 100,
      'damage_dice' => '1d8+50',
      'damage_type' => 'slashing',
    ],
  ]);

  assert_true(!empty($strike_result['success']), 'Strike action succeeds');
  assert_true(!empty($strike_result['result']['strike']), 'Strike result is returned');
  assert_true(!empty($strike_result['phase_transition']), 'Strike triggers encounter end transition');
  assert_equals('encounter', $strike_result['phase_transition']['from'] ?? NULL, 'Transition starts from encounter');
  assert_equals('exploration', $strike_result['phase_transition']['to'] ?? NULL, 'Transition returns to exploration');
  assert_equals('exploration', $strike_result['game_state']['phase'] ?? NULL, 'Game state returns to exploration');
  assert_true(empty($strike_result['game_state']['encounter_id']), 'Encounter id is cleared after full combat cycle');
  assert_true(!empty($strike_result['game_state']['last_encounter']), 'Last encounter summary retained after full cycle');

  $damage_row = $db->select('combat_damage_log', 'd')
    ->fields('d', ['amount', 'hp_after'])
    ->condition('encounter_id', (int) ($combat_state['encounter_id'] ?? 0))
    ->orderBy('created', 'DESC')
    ->range(0, 1)
    ->execute()
    ->fetchAssoc();
  assert_true(!empty($damage_row), 'Damage log entry created during combat cycle');

  $enemy_row = $db->select('combat_participants', 'p')
    ->fields('p', ['is_defeated', 'hp'])
    ->condition('encounter_id', (int) ($combat_state['encounter_id'] ?? 0))
    ->condition('entity_id', $enemy_id)
    ->execute()
    ->fetchAssoc();
  assert_true(!empty($enemy_row) && (int) ($enemy_row['is_defeated'] ?? 0) === 1, 'Enemy is marked defeated by the strike');

  echo "\n--- Stage 3: Narrative resumes ---\n";
  $available = $strike_result['available_actions'] ?? [];
  assert_true(in_array('move', $available, TRUE), 'Exploration move action is available again');
  assert_true(in_array('talk', $available, TRUE), 'Exploration talk action is available again');
}
catch (Throwable $e) {
  assert_true(FALSE, 'Unhandled exception: ' . $e->getMessage());
}
finally {
  if ($campaign_id > 0) {
    cleanup_cycle_campaign($db, $campaign_id);
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
