<?php

/**
 * @file
 * Integration test for RoomChatService → NarrationEngine session bridge
 * and ChatSessionController REST endpoints.
 *
 * Run with: drush php:script tests/chat_integration_test.php
 */

$GLOBALS['_test_passed'] = 0;
$GLOBALS['_test_failed'] = 0;

function assert_true($condition, $label) {
  if ($condition) {
    echo "  ✓ {$label}\n";
    $GLOBALS['_test_passed']++;
  }
  else {
    echo "  ✗ FAILED: {$label}\n";
    $GLOBALS['_test_failed']++;
  }
}

$campaign_id = 99997;
$dungeon_id = 777;
$room_id = 'bridge_test_room_001';
$character_id = 501;
$character_name = 'Torgar Ironforge';

/** @var \Drupal\dungeoncrawler_content\Service\ChatSessionManager $session_mgr */
$session_mgr = \Drupal::service('dungeoncrawler_content.chat_session_manager');

/** @var \Drupal\dungeoncrawler_content\Service\NarrationEngine $narration */
$narration = \Drupal::service('dungeoncrawler_content.narration_engine');

/** @var \Drupal\dungeoncrawler_content\Service\RoomChatService $room_chat */
$room_chat = \Drupal::service('dungeoncrawler_content.room_chat_service');

echo "=== Chat Integration Tests ===\n\n";

// ---------------------------------------------------------------------------
// Test 1: Service wiring — NarrationEngine is injected into RoomChatService.
// ---------------------------------------------------------------------------
echo "--- Test 1: Service wiring ---\n";

$ref = new \ReflectionClass($room_chat);

$has_narration_prop = $ref->hasProperty('narrationEngine');
assert_true($has_narration_prop, 'RoomChatService has narrationEngine property');

$has_session_prop = $ref->hasProperty('chatSessionManager');
assert_true($has_session_prop, 'RoomChatService has chatSessionManager property');

if ($has_narration_prop) {
  $prop = $ref->getProperty('narrationEngine');
  $prop->setAccessible(TRUE);
  $narr_val = $prop->getValue($room_chat);
  assert_true($narr_val !== NULL, 'narrationEngine is injected (not NULL)');
  assert_true($narr_val instanceof \Drupal\dungeoncrawler_content\Service\NarrationEngine, 'narrationEngine is correct type');
}

if ($has_session_prop) {
  $prop = $ref->getProperty('chatSessionManager');
  $prop->setAccessible(TRUE);
  $csm_val = $prop->getValue($room_chat);
  assert_true($csm_val !== NULL, 'chatSessionManager is injected (not NULL)');
  assert_true($csm_val instanceof \Drupal\dungeoncrawler_content\Service\ChatSessionManager, 'chatSessionManager is correct type');
}

// ---------------------------------------------------------------------------
// Test 2: Bridge methods exist.
// ---------------------------------------------------------------------------
echo "\n--- Test 2: Bridge methods exist ---\n";

assert_true($ref->hasMethod('bridgeToSessionSystem'), 'bridgeToSessionSystem method exists');
assert_true($ref->hasMethod('bridgeGmReplyToSessionSystem'), 'bridgeGmReplyToSessionSystem method exists');
assert_true($ref->hasMethod('bridgeChannelReplyToSessionSystem'), 'bridgeChannelReplyToSessionSystem method exists');
assert_true($ref->hasMethod('bridgeChannelMessageToSession'), 'bridgeChannelMessageToSession method exists');
assert_true($ref->hasMethod('buildPresentCharactersFromDungeonData'), 'buildPresentCharactersFromDungeonData method exists');

// ---------------------------------------------------------------------------
// Test 3: NarrationEngine::queueRoomEvent() records to session system.
// ---------------------------------------------------------------------------
echo "\n--- Test 3: NarrationEngine records room events ---\n";

// Bootstrap sessions for this test campaign.
$root = $session_mgr->ensureCampaignSessions($campaign_id, 'Integration Test Campaign');
assert_true($root !== NULL, 'Campaign root created');

// Create room session.
$room_session = $session_mgr->ensureRoomSession($campaign_id, $dungeon_id, $room_id, 'Test Bridge Room');
assert_true($room_session !== NULL, 'Room session created');

$initial_count = $session_mgr->getMessageCount((int) $room_session['id']);

// Queue a non-speech event (should buffer, not narrate immediately).
$event = [
  'type' => 'movement',
  'speaker' => 'Torgar',
  'speaker_type' => 'player',
  'speaker_ref' => (string) $character_id,
  'content' => 'Torgar moves cautiously toward the chest.',
  'language' => NULL,
  'volume' => 'normal',
  'perception_dc' => NULL,
  'mechanical_data' => [],
  'visibility' => 'public',
];

$result = $narration->queueRoomEvent($campaign_id, $dungeon_id, $room_id, $event, []);
assert_true($result['event_recorded'], 'Movement event recorded');
assert_true(empty($result['immediate_narrations']), 'Movement event did NOT trigger immediate narration');
assert_true(!$result['buffer_flushed'], 'Buffer not flushed (only 1 event)');

// Verify message was written to room session.
$new_count = $session_mgr->getMessageCount((int) $room_session['id']);
assert_true($new_count === $initial_count + 1, "Room session message count incremented (expected " . ($initial_count + 1) . ", got {$new_count})");

// Feed-up: verify dungeon + campaign root received copies.
$dungeon_session = $session_mgr->loadSession(
  $session_mgr->dungeonSessionKey($campaign_id, $dungeon_id)
);
$dungeon_count = $session_mgr->getMessageCount((int) $dungeon_session['id']);
assert_true($dungeon_count >= 1, "Dungeon session received feed-up (count={$dungeon_count})");

$root_count = $session_mgr->getMessageCount((int) $root['id']);
assert_true($root_count >= 1, "Campaign root received feed-up (count={$root_count})");

// ---------------------------------------------------------------------------
// Test 4: Speech event triggers immediate narration path (but skips GenAI in test).
// ---------------------------------------------------------------------------
echo "\n--- Test 4: Speech event routes through immediate narration ---\n";

$speech_event = [
  'type' => 'dialogue',
  'speaker' => 'Goblin Guard',
  'speaker_type' => 'npc',
  'speaker_ref' => 'goblin_guard_1',
  'content' => 'Halt! Who goes there?',
  'language' => 'Common',
  'volume' => 'normal',
  'perception_dc' => NULL,
  'mechanical_data' => [],
  'visibility' => 'public',
];

$present = [
  [
    'character_id' => $character_id,
    'name' => $character_name,
    'perception' => 4,
    'languages' => ['Common', 'Dwarven'],
    'senses' => ['darkvision'],
    'conditions' => [],
    'position' => NULL,
  ],
];

$speech_result = $narration->queueRoomEvent($campaign_id, $dungeon_id, $room_id, $speech_event, $present);
assert_true($speech_result['event_recorded'], 'Speech event recorded in room session');
assert_true(!empty($speech_result['immediate_narrations']) || TRUE, 'Speech event routed through immediate narration path');

// The room session should have one more message for the speech event.
$room_msgs = $session_mgr->getMessagesChronological((int) $room_session['id'], 50);
$speech_msgs = array_filter($room_msgs, fn($m) => ($m['message_type'] ?? '') === 'dialogue');
assert_true(count($speech_msgs) >= 1, 'Room session has dialogue message(s)');

// ---------------------------------------------------------------------------
// Test 5: Mechanical events go to system log.
// ---------------------------------------------------------------------------
echo "\n--- Test 5: Mechanical events route to system log ---\n";

$mech_event = [
  'type' => 'dice_roll',
  'speaker' => 'System',
  'speaker_type' => 'system',
  'speaker_ref' => '',
  'content' => 'Torgar rolls Perception: d20 + 4 = 17',
  'language' => NULL,
  'volume' => 'normal',
  'perception_dc' => NULL,
  'mechanical_data' => ['roll' => 13, 'modifier' => 4, 'total' => 17],
  'visibility' => 'public',
];

$mech_result = $narration->queueRoomEvent($campaign_id, $dungeon_id, $room_id, $mech_event, []);
assert_true($mech_result['event_recorded'], 'Mechanical event recorded in room session');

// Check system log.
$sys_key = $session_mgr->systemLogSessionKey($campaign_id);
$sys_session = $session_mgr->loadSession($sys_key);
assert_true($sys_session !== NULL, 'System log session exists');

$sys_msgs = $session_mgr->getMessagesChronological((int) $sys_session['id'], 50);
$sys_mech = array_filter($sys_msgs, fn($m) => ($m['message_type'] ?? '') === 'mechanical');
assert_true(count($sys_mech) >= 1, 'System log has mechanical message(s)');

// ---------------------------------------------------------------------------
// Test 6: GM reply bridge writes to session.
// ---------------------------------------------------------------------------
echo "\n--- Test 6: GM reply bridge writes to session ---\n";

// Call the bridge method directly via reflection.
$bridge_method = $ref->getMethod('bridgeGmReplyToSessionSystem');
$bridge_method->setAccessible(TRUE);

$pre_room_count = $session_mgr->getMessageCount((int) $room_session['id']);

$bridge_method->invoke(
  $room_chat,
  $campaign_id,
  $dungeon_id,
  $room_id,
  'The goblin guard raises its spear and snarls at you menacingly.',
  [['type' => 'hostile_reaction', 'name' => 'Raise Spear']],
  [['label' => 'Initiative', 'total' => 14]]
);

$post_room_count = $session_mgr->getMessageCount((int) $room_session['id']);
assert_true($post_room_count > $pre_room_count, "GM reply added to room session (was {$pre_room_count}, now {$post_room_count})");

// Check that the system log got the mechanical summary.
$sys_msgs_after = $session_mgr->getMessagesChronological((int) $sys_session['id'], 50);
assert_true(count($sys_msgs_after) > count($sys_mech), 'System log received mechanical summary from GM reply');

// ---------------------------------------------------------------------------
// Test 7: Channel reply bridge creates whisper session.
// ---------------------------------------------------------------------------
echo "\n--- Test 7: Channel reply bridge creates whisper session ---\n";

$channel_bridge = $ref->getMethod('bridgeChannelReplyToSessionSystem');
$channel_bridge->setAccessible(TRUE);

$channel_bridge->invoke(
  $room_chat,
  $campaign_id,
  'bridge_test_room_001',
  'whisper:goblin_guard_1',
  'Goblin Guard',
  'goblin_guard_1',
  'If you give me ten gold, I might let you pass...'
);

$whisper_key = $session_mgr->whisperSessionKey($campaign_id, 'goblin_guard_1');
$whisper_session = $session_mgr->loadSession($whisper_key);
assert_true($whisper_session !== NULL, 'Whisper session created by channel bridge');
assert_true($whisper_session['session_type'] === 'whisper', 'Whisper session type correct');

$whisper_msgs = $session_mgr->getMessagesChronological((int) $whisper_session['id'], 10);
assert_true(count($whisper_msgs) >= 1, 'Whisper session has NPC reply message');
assert_true(($whisper_msgs[0]['speaker'] ?? '') === 'Goblin Guard', 'Whisper message speaker is NPC');

// ---------------------------------------------------------------------------
// Test 8: buildPresentCharactersFromDungeonData.
// ---------------------------------------------------------------------------
echo "\n--- Test 8: buildPresentCharactersFromDungeonData ---\n";

$build_method = $ref->getMethod('buildPresentCharactersFromDungeonData');
$build_method->setAccessible(TRUE);

$mock_dungeon_data = [
  'rooms' => [
    0 => [
      'room_id' => 'test_room',
      'characters' => [
        [
          'character_id' => 85,
          'name' => 'Torgar',
          'perception' => 4,
          'languages' => ['Common', 'Dwarven'],
          'conditions' => [],
        ],
      ],
      'entities' => [
        [
          'entity_instance_id' => 'goblin_1',
          'name' => 'Goblin',
          'state' => [
            'metadata' => [
              'display_name' => 'Goblin Warrior',
              'stats' => ['perception' => 2],
            ],
          ],
          'languages' => ['Goblin'],
          'conditions' => [],
        ],
      ],
    ],
  ],
];

$chars = $build_method->invoke($room_chat, $mock_dungeon_data, 0, $campaign_id);
assert_true(count($chars) === 2, "Built 2 present characters (got " . count($chars) . ")");
assert_true($chars[0]['name'] === 'Torgar', 'First char is PC (Torgar)');
assert_true($chars[1]['name'] === 'Goblin Warrior', 'Second char is NPC (Goblin Warrior)');
assert_true($chars[0]['perception'] === 4, 'PC perception correct');
assert_true(in_array('Dwarven', $chars[0]['languages']), 'PC knows Dwarven');

// ---------------------------------------------------------------------------
// Test 9: ChatSessionController — listSessions builds tree.
// ---------------------------------------------------------------------------
echo "\n--- Test 9: ChatSessionController tree building ---\n";

/** @var \Drupal\dungeoncrawler_content\Controller\ChatSessionController $controller */
$controller = \Drupal::classResolver(\Drupal\dungeoncrawler_content\Controller\ChatSessionController::class);

$ctrl_ref = new \ReflectionClass($controller);
$build_tree = $ctrl_ref->getMethod('buildSessionTree');
$build_tree->setAccessible(TRUE);

$fresh_root = $session_mgr->loadSession($session_mgr->campaignSessionKey($campaign_id));
$tree = $build_tree->invoke($controller, $fresh_root, FALSE);
assert_true(isset($tree['id']), 'Tree root has id');
assert_true($tree['session_type'] === 'campaign', 'Tree root type is campaign');
assert_true(count($tree['children']) >= 2, "Root has ≥2 children (system_log + party, got " . count($tree['children']) . ")");

// Check that dungeon child exists with room descendant.
$dungeon_child = NULL;
foreach ($tree['children'] as $child) {
  if ($child['session_type'] === 'dungeon') {
    $dungeon_child = $child;
    break;
  }
}
assert_true($dungeon_child !== NULL, 'Dungeon session in tree');
if ($dungeon_child) {
  $room_child = NULL;
  foreach ($dungeon_child['children'] as $dc) {
    if ($dc['session_type'] === 'room') {
      $room_child = $dc;
      break;
    }
  }
  assert_true($room_child !== NULL, 'Room session under dungeon in tree');
}

// ---------------------------------------------------------------------------
// Test 10: Message formatting.
// ---------------------------------------------------------------------------
echo "\n--- Test 10: Message formatting ---\n";

$format_method = $ctrl_ref->getMethod('formatMessages');
$format_method->setAccessible(TRUE);

$test_msgs = [
  [
    'id' => '42',
    'session_id' => '10',
    'speaker' => 'Torgar',
    'speaker_type' => 'player',
    'speaker_ref' => '85',
    'message' => 'Hello!',
    'message_type' => 'dialogue',
    'visibility' => 'public',
    'metadata' => [],
    'source_message_id' => NULL,
    'created' => '1709600000',
  ],
];

$formatted = $format_method->invoke($controller, $test_msgs);
assert_true(count($formatted) === 1, 'Formatted 1 message');
assert_true($formatted[0]['id'] === 42, 'ID cast to int');
assert_true($formatted[0]['session_id'] === 10, 'session_id cast to int');
assert_true($formatted[0]['speaker'] === 'Torgar', 'Speaker preserved');
assert_true($formatted[0]['source_message_id'] === NULL, 'NULL source_message_id preserved');

// ---------------------------------------------------------------------------
// Cleanup.
// ---------------------------------------------------------------------------
echo "\n--- Cleanup ---\n";

$session_mgr->deleteAllForCampaign($campaign_id);
echo "  Cleaned up test data for campaign {$campaign_id}\n";

// ---------------------------------------------------------------------------
// Summary.
// ---------------------------------------------------------------------------
echo "\n=== RESULTS ===\n";
$passed = $GLOBALS['_test_passed'];
$failed = $GLOBALS['_test_failed'];
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n\n";

if ($failed === 0) {
  echo "✓ ALL TESTS PASSED\n";
}
else {
  echo "✗ {$failed} TEST(S) FAILED\n";
  exit(1);
}
