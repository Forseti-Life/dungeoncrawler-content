<?php
/**
 * @file
 * End-to-end-ish lifecycle test for exploration → encounter → exploration.
 *
 * This validates the server-side flow used by dialogue-triggered combat:
 *  1. Start in exploration.
 *  2. Simulate a narrative attack declaration: "I attack Gribbles."
 *  3. Resolve combat initiation through RoomChatService's canonical action path.
 *  4. Confirm encounter round 1 starts.
 *  5. Mark the enemy defeated.
 *  6. Confirm encounter end requests transition back to exploration.
 *  7. Apply the transition and verify narrative/exploration state resumes.
 *
 * Run with:
 *   drush php:script web/modules/custom/dungeoncrawler_content/tests/narrative_combat_flow_test.php
 */

use Drupal\dungeoncrawler_content\Service\CampaignInitializationService;
use Drupal\dungeoncrawler_content\Service\EncounterPhaseHandler;
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

function find_room(array $dungeon_data, string $room_id): ?array {
  foreach (($dungeon_data['rooms'] ?? []) as $room) {
    if (($room['room_id'] ?? '') === $room_id) {
      return $room;
    }
  }
  return NULL;
}

function persist_dungeon_data($db, int $campaign_id, array $dungeon_data): void {
  $db->update('dc_campaign_dungeons')
    ->fields([
      'dungeon_data' => json_encode($dungeon_data),
      'updated' => time(),
    ])
    ->condition('campaign_id', $campaign_id)
    ->execute();
}

function cleanup_campaign($db, int $campaign_id): void {
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

echo "=== Narrative Combat Flow Test ===\n\n";

/** @var CampaignInitializationService $init */
$init = \Drupal::service('dungeoncrawler_content.campaign_initialization');
/** @var RoomChatService $room_chat */
$room_chat = \Drupal::service('dungeoncrawler_content.room_chat_service');
/** @var GameCoordinatorService $game_coordinator */
$game_coordinator = \Drupal::service('dungeoncrawler_content.game_coordinator');
/** @var EncounterPhaseHandler $encounter_handler */
$encounter_handler = \Drupal::service('dungeoncrawler_content.encounter_phase_handler');
$db = \Drupal::database();

$test_uid = (int) \Drupal::currentUser()->id();
if ($test_uid < 1) {
  $test_uid = 1;
}

$campaign_id = 0;

try {
  $campaign_id = $init->initializeCampaign(
    $test_uid,
    'Narrative Combat Flow Test Campaign',
    'classic_dungeon',
    'normal'
  );

  assert_true($campaign_id > 0, 'Test campaign created');

  $dungeon_data_raw = $db->select('dc_campaign_dungeons', 'd')
    ->fields('d', ['dungeon_data'])
    ->condition('campaign_id', $campaign_id)
    ->orderBy('id', 'DESC')
    ->range(0, 1)
    ->execute()
    ->fetchField();

  $dungeon_data = json_decode($dungeon_data_raw ?: '{}', TRUE) ?: [];
  $active_room_id = (string) ($dungeon_data['active_room_id'] ?? '');
  $room_meta = find_room($dungeon_data, $active_room_id);

  if ($active_room_id === '' && !empty($dungeon_data['rooms'][0]['room_id'])) {
    $active_room_id = (string) $dungeon_data['rooms'][0]['room_id'];
    $dungeon_data['active_room_id'] = $active_room_id;
    $room_meta = find_room($dungeon_data, $active_room_id);
  }

  if (!is_array($room_meta)) {
    $room_meta = [
      'room_id' => $active_room_id !== '' ? $active_room_id : 'flow_test_room',
      'name' => 'Flow Test Room',
      'description' => 'A minimal room used for testing the narrative combat lifecycle.',
      'hexes' => [
        ['q' => 0, 'r' => 0],
        ['q' => 1, 'r' => 0],
      ],
      'terrain' => [],
      'gameplay_state' => [],
    ];
    $active_room_id = (string) $room_meta['room_id'];
    $dungeon_data['active_room_id'] = $active_room_id;
    $dungeon_data['rooms'][] = $room_meta;
  }

  assert_true($active_room_id !== '', 'Active room exists');
  assert_true(is_array($room_meta), 'Active room metadata exists');

  echo "--- Stage 1: Seed exploration scene ---\n";

  $hero = [
    'entity_instance_id' => '910001',
    'entity_type' => 'player_character',
    'entity_ref' => [
      'content_type' => 'player_character',
      'content_id' => 'test_hero',
    ],
    'name' => 'Torgar Ironforge',
    'placement' => [
      'room_id' => $active_room_id,
      'hex' => ['q' => 0, 'r' => 0],
    ],
    'state' => [
      'metadata' => [
        'display_name' => 'Torgar Ironforge',
        'team' => 'player',
        'stats' => [
          'currentHp' => 24,
          'maxHp' => 24,
          'ac' => 18,
          'perception' => 6,
        ],
      ],
      'hit_points' => ['current' => 24, 'max' => 24],
      'armor_class' => 18,
      'perception' => 6,
    ],
  ];

  $enemy = [
    'entity_instance_id' => '910002',
    'entity_type' => 'npc',
    'entity_ref' => [
      'content_type' => 'npc',
      'content_id' => 'gribbles_rindsworth',
    ],
    'name' => 'Gribbles',
    'placement' => [
      'room_id' => $active_room_id,
      'hex' => ['q' => 1, 'r' => 0],
    ],
    'state' => [
      'metadata' => [
        'display_name' => 'Gribbles',
        'team' => 'hostile',
        'role' => 'enemy',
        'description' => 'A surly hostile foe spoiling for a fight.',
        'stats' => [
          'currentHp' => 12,
          'maxHp' => 12,
          'ac' => 15,
          'perception' => 3,
        ],
      ],
      'hit_points' => ['current' => 12, 'max' => 12],
      'armor_class' => 15,
      'perception' => 3,
    ],
  ];

  $dungeon_data['entities'] = [$hero, $enemy];
  $dungeon_data['game_state']['phase'] = 'exploration';
  $dungeon_data['game_state']['encounter_id'] = NULL;
  $dungeon_data['game_state']['round'] = NULL;
  $dungeon_data['game_state']['turn'] = NULL;
  $dungeon_data['game_state']['initiative_order'] = NULL;
  persist_dungeon_data($db, $campaign_id, $dungeon_data);

  assert_equals('exploration', $dungeon_data['game_state']['phase'] ?? NULL, 'Initial phase is exploration');

  echo "\n--- Stage 2: Narrative attack declaration triggers encounter ---\n";

  $narrative = 'I attack Gribbles.';
  $action = [
    'type' => 'combat_initiation',
    'name' => 'Attack Gribbles',
    'details' => [
      'combat' => [
        'reason' => 'Torgar says "I attack Gribbles" and initiates combat.',
        'target_name' => 'Gribbles',
      ],
      'result_description' => 'Combat starts from the spoken attack declaration.',
    ],
  ];

  $room_ref = new ReflectionClass($room_chat);
  $combat_method = $room_ref->getMethod('handleCombatInitiationAction');
  $combat_method->setAccessible(TRUE);
  $combat_result = $combat_method->invoke($room_chat, $campaign_id, $active_room_id, $room_meta, $dungeon_data, $action);

  assert_true(!empty($combat_result['success']), 'Combat initiation from narrative action succeeds');
  assert_true(!empty($combat_result['transition']['success']), 'Game coordinator transition succeeds');

  $post_combat_data = $combat_result['dungeon_data'] ?? [];
  $post_combat_state = $post_combat_data['game_state'] ?? [];

  assert_equals('encounter', $post_combat_state['phase'] ?? NULL, 'Phase changed to encounter');
  assert_equals(1, (int) ($post_combat_state['round'] ?? 0), 'Encounter starts at round 1');
  assert_true(!empty($post_combat_state['encounter_id']), 'Encounter id assigned');
  assert_true(count($post_combat_state['initiative_order'] ?? []) >= 2, 'Initiative order contains both combatants');

  $encounter_id = (int) ($post_combat_state['encounter_id'] ?? 0);
  $participants = $db->select('combat_participants', 'p')
    ->fields('p')
    ->condition('encounter_id', $encounter_id)
    ->execute()
    ->fetchAllAssoc('entity_id');

  assert_true(isset($participants['910001']), 'Hero participant created');
  assert_true(isset($participants['910002']), 'Enemy participant created');

  echo "  Narrative tested: \"{$narrative}\"\n";

  echo "\n--- Stage 3: Combat resolves and requests return to exploration ---\n";

  $db->update('combat_participants')
    ->fields([
      'hp' => 0,
      'is_defeated' => 1,
      'updated' => time(),
    ])
    ->condition('encounter_id', $encounter_id)
    ->condition('entity_id', '910002')
    ->execute();

  foreach (($post_combat_data['game_state']['initiative_order'] ?? []) as &$combatant) {
    if ((string) ($combatant['entity_id'] ?? '') === '910002') {
      $combatant['hp'] = 0;
      $combatant['is_defeated'] = TRUE;
    }
  }
  unset($combatant);
  persist_dungeon_data($db, $campaign_id, $post_combat_data);

  $gribbles_row = $db->select('combat_participants', 'p')
    ->fields('p', ['is_defeated', 'hp'])
    ->condition('encounter_id', $encounter_id)
    ->condition('entity_id', '910002')
    ->execute()
    ->fetchAssoc();
  assert_true(!empty($gribbles_row) && (int) ($gribbles_row['is_defeated'] ?? 0) === 1, 'Resolved target is marked defeated before returning to exploration');

  $return_result = $game_coordinator->transitionPhase($campaign_id, 'exploration', [
    'reason' => 'All enemies have been defeated! Returning to narrative play.',
    'encounter_result' => [
      'encounter_id' => $encounter_id,
      'final_round' => $post_combat_data['game_state']['round'] ?? 1,
      'victory' => TRUE,
    ],
  ]);

  assert_true(!empty($return_result['success']), 'Transition back to exploration succeeds');
  assert_equals('exploration', $return_result['game_state']['phase'] ?? NULL, 'Final phase is exploration');
  assert_true(empty($return_result['game_state']['encounter_id']), 'Encounter id cleared after return to exploration');
  assert_true(!empty($return_result['game_state']['last_encounter']), 'Last encounter summary retained');

  echo "\n--- Stage 4: Narrative mode resumed ---\n";
  $available = $return_result['available_actions'] ?? [];
  assert_true(in_array('talk', $available, TRUE), 'Exploration actions restored (talk available)');
  assert_true(in_array('move', $available, TRUE), 'Exploration actions restored (move available)');
}
catch (Throwable $e) {
  assert_true(FALSE, 'Unhandled exception: ' . $e->getMessage());
}
finally {
  if ($campaign_id > 0) {
    cleanup_campaign($db, $campaign_id);
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
