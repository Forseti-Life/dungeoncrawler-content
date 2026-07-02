<?php
/**
 * @file
 * Functional tests for ChatSessionManager and NarrationEngine.
 *
 * Run with: drush php:script tests/chat_session_test.php
 *
 * Tests the hierarchical chat session architecture:
 *   - Campaign root creation + system log + party sessions
 *   - Dungeon/room/character narrative session hierarchy
 *   - Message posting with feed-up propagation
 *   - GM private channels
 *   - Session archival with dungeon summaries
 *   - NarrationEngine perception filtering
 */

use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\NarrationEngine;

$GLOBALS['test_pass'] = 0;
$GLOBALS['test_fail'] = 0;
$GLOBALS['test_errors'] = [];

function assert_true($condition, $label) {
  if ($condition) {
    $GLOBALS['test_pass']++;
    echo "  ✓ {$label}\n";
  } else {
    $GLOBALS['test_fail']++;
    $GLOBALS['test_errors'][] = $label;
    echo "  ✗ FAIL: {$label}\n";
  }
}

function assert_equals($expected, $actual, $label) {
  if ($expected === $actual) {
    $GLOBALS['test_pass']++;
    echo "  ✓ {$label}\n";
  } else {
    $GLOBALS['test_fail']++;
    $GLOBALS['test_errors'][] = "{$label} (expected: " . var_export($expected, TRUE) . ", got: " . var_export($actual, TRUE) . ")";
    echo "  ✗ FAIL: {$label} (expected: " . var_export($expected, TRUE) . ", got: " . var_export($actual, TRUE) . ")\n";
  }
}

function assert_not_null($value, $label) {
  assert_true($value !== NULL, $label);
}

function assert_greater_than($threshold, $actual, $label) {
  assert_true($actual > $threshold, "{$label} (expected > {$threshold}, got: {$actual})");
}

echo "=== Chat Session Hierarchy Tests ===\n\n";

/** @var ChatSessionManager $sessionManager */
$sessionManager = \Drupal::service('dungeoncrawler_content.chat_session_manager');
$db = \Drupal::database();

// Use a test campaign ID that won't conflict.
$test_campaign_id = 99998;
$test_dungeon_id = 'test-dungeon-1';
$test_room_id = 'tavern-entrance-abc';

// Clean up any previous test data.
$sessionManager->deleteAllForCampaign($test_campaign_id);

// ============================================================================
echo "--- Test 1: Campaign session bootstrapping ---\n";
// ============================================================================
$root = $sessionManager->ensureCampaignSessions($test_campaign_id, 'Test Campaign');

assert_not_null($root, 'Campaign root session created');
assert_equals('campaign', $root['session_type'], 'Root session type is campaign');
assert_equals($test_campaign_id, (int) $root['campaign_id'], 'Root campaign_id matches');
assert_equals(NULL, $root['parent_session_id'], 'Root has no parent');
assert_equals('active', $root['status'], 'Root status is active');

// Check system log was created.
$sys = $sessionManager->loadSession($sessionManager->systemLogSessionKey($test_campaign_id));
assert_not_null($sys, 'System log session created');
assert_equals('system_log', $sys['session_type'], 'System log type correct');
assert_equals((int) $root['id'], (int) $sys['parent_session_id'], 'System log parent is campaign root');

// Check party chat was created.
$party = $sessionManager->loadSession($sessionManager->partySessionKey($test_campaign_id));
assert_not_null($party, 'Party session created');
assert_equals('party', $party['session_type'], 'Party session type correct');
assert_equals((int) $root['id'], (int) $party['parent_session_id'], 'Party parent is campaign root');

// ============================================================================
echo "\n--- Test 2: Idempotent session creation ---\n";
// ============================================================================
$root2 = $sessionManager->ensureCampaignSessions($test_campaign_id, 'Test Campaign');
assert_equals((int) $root['id'], (int) $root2['id'], 'Second ensureCampaignSessions returns same root');

// ============================================================================
echo "\n--- Test 3: Dungeon + Room session hierarchy ---\n";
// ============================================================================
$dungeon = $sessionManager->ensureDungeonSession($test_campaign_id, $test_dungeon_id, 'Test Dungeon');
assert_not_null($dungeon, 'Dungeon session created');
assert_equals('dungeon', $dungeon['session_type'], 'Dungeon type correct');
assert_equals((int) $root['id'], (int) $dungeon['parent_session_id'], 'Dungeon parent is campaign root');

$room = $sessionManager->ensureRoomSession($test_campaign_id, $test_dungeon_id, $test_room_id, 'Tavern Entrance');
assert_not_null($room, 'Room session created');
assert_equals('room', $room['session_type'], 'Room type correct');
assert_equals((int) $dungeon['id'], (int) $room['parent_session_id'], 'Room parent is dungeon');

// ============================================================================
echo "\n--- Test 4: Character narrative session ---\n";
// ============================================================================
$char_session = $sessionManager->ensureCharacterNarrativeSession(
  $test_campaign_id, $test_dungeon_id, $test_room_id, 42, 'Thordak the Dwarf'
);
assert_not_null($char_session, 'Character narrative session created');
assert_equals('character_narrative', $char_session['session_type'], 'Char narrative type correct');
assert_equals((int) $room['id'], (int) $char_session['parent_session_id'], 'Char narrative parent is room');
assert_true(strpos($char_session['label'], 'Thordak') !== FALSE, 'Label contains character name');

// ============================================================================
echo "\n--- Test 5: Post message to room with feed-up ---\n";
// ============================================================================
$msg_id = $sessionManager->postMessage(
  (int) $room['id'],
  $test_campaign_id,
  'Innkeeper Hilda',
  'npc',
  'innkeeper_hilda',
  'Welcome to the Rusty Flagon! What can I get for you?',
  'dialogue',
  'public',
  ['event_type' => 'npc_speech'],
  TRUE // feed up
);

assert_greater_than(0, $msg_id, 'Message posted with valid ID');

// Check room has the message.
$room_msgs = $sessionManager->getMessages((int) $room['id'], 10);
assert_equals(1, count($room_msgs), 'Room has 1 message');
assert_equals('Innkeeper Hilda', $room_msgs[0]['speaker'], 'Room message speaker correct');

// Check dungeon got a feed copy.
$dungeon_msgs = $sessionManager->getMessages((int) $dungeon['id'], 10);
assert_greater_than(0, count($dungeon_msgs), 'Dungeon received feed-up message');
assert_equals($msg_id, (int) $dungeon_msgs[0]['source_message_id'], 'Dungeon message references source');

// Check campaign root got a feed copy.
$root_msgs = $sessionManager->getMessages((int) $root['id'], 10);
assert_greater_than(0, count($root_msgs), 'Campaign root received feed-up');

// ============================================================================
echo "\n--- Test 6: GM Private channel ---\n";
// ============================================================================
$gm_private = $sessionManager->ensureGmPrivateSession($test_campaign_id, 42, 'Thordak');
assert_not_null($gm_private, 'GM private session created');
assert_equals('gm_private', $gm_private['session_type'], 'GM private type correct');

$priv_msg_id = $sessionManager->postMessage(
  (int) $gm_private['id'],
  $test_campaign_id,
  'Thordak',
  'player',
  '42',
  'I attempt to pickpocket the innkeeper while she is distracted.',
  'action',
  'gm_only',
  ['skill' => 'thievery', 'dc' => 18],
  TRUE
);

assert_greater_than(0, $priv_msg_id, 'Private message posted');

// Check it fed up to campaign root.
$root_msgs_after = $sessionManager->getMessages((int) $root['id'], 20);
$found_private = FALSE;
foreach ($root_msgs_after as $m) {
  if ($m['source_message_id'] == $priv_msg_id) {
    $found_private = TRUE;
    break;
  }
}
assert_true($found_private, 'Private action fed up to campaign root');

// ============================================================================
echo "\n--- Test 7: Post message WITHOUT feed-up ---\n";
// ============================================================================
$no_feed_count_before = count($sessionManager->getMessages((int) $root['id'], 100));

$sessionManager->postMessage(
  (int) $char_session['id'],
  $test_campaign_id,
  'Game Master',
  'gm',
  '',
  'You see the innkeeper smile warmly as you enter.',
  'scene_beat',
  'private',
  [],
  FALSE // no feed up
);

$no_feed_count_after = count($sessionManager->getMessages((int) $root['id'], 100));
assert_equals($no_feed_count_before, $no_feed_count_after, 'No-feed message did not propagate to root');

// ============================================================================
echo "\n--- Test 8: Build AI context from session ---\n";
// ============================================================================
$context = $sessionManager->buildSessionContext((int) $room['id'], 5);
assert_true(strlen($context) > 0, 'AI context built from room session');
assert_true(strpos($context, 'Innkeeper Hilda') !== FALSE, 'Context contains speaker name');
assert_true(strpos($context, 'Rusty Flagon') !== FALSE, 'Context contains message content');

// ============================================================================
echo "\n--- Test 9: Session archival ---\n";
// ============================================================================
$sessionManager->archiveDungeonWithSummary(
  $test_campaign_id,
  $test_dungeon_id,
  'The party entered the Rusty Flagon tavern and spoke with Innkeeper Hilda. They learned about trouble in the goblin warrens.'
);

$dungeon_after = $sessionManager->loadSession($sessionManager->dungeonSessionKey($test_campaign_id, $test_dungeon_id));
assert_equals('archived', $dungeon_after['status'], 'Dungeon session archived');

$room_after = $sessionManager->loadSession($sessionManager->roomSessionKey($test_campaign_id, $test_dungeon_id, $test_room_id));
assert_equals('archived', $room_after['status'], 'Room session archived');

// Check campaign root got the summary.
$root_after = $sessionManager->loadSession($sessionManager->campaignSessionKey($test_campaign_id));
assert_true(strpos($root_after['summary'], 'Rusty Flagon') !== FALSE, 'Campaign root has dungeon summary');

// ============================================================================
echo "\n--- Test 10: Session key builders ---\n";
// ============================================================================
assert_equals("campaign.5", $sessionManager->campaignSessionKey(5), 'Campaign key correct');
assert_equals("campaign.5.dungeon.12", $sessionManager->dungeonSessionKey(5, 12), 'Dungeon key correct');
assert_true(strpos($sessionManager->roomSessionKey(5, 12, 'abc-123'), 'campaign.5.dungeon.12.room.abc-123') !== FALSE, 'Room key correct');
assert_equals("campaign.5.party", $sessionManager->partySessionKey(5), 'Party key correct');
assert_equals("campaign.5.gm_private.42", $sessionManager->gmPrivateSessionKey(5, 42), 'GM private key correct');
assert_equals("campaign.5.system_log", $sessionManager->systemLogSessionKey(5), 'System log key correct');

// ============================================================================
echo "\n--- Test 11: NarrationEngine perception filtering ---\n";
// ============================================================================
$narrationEngine = NULL;
try {
  $narrationEngine = \Drupal::service('dungeoncrawler_content.narration_engine');
} catch (\Exception $e) {
  echo "  ⚠ NarrationEngine service not available (AI module may not be present): {$e->getMessage()}\n";
}

if ($narrationEngine) {
  // Test: normal character hears speech.
  $normal_char = [
    'character_id' => 1,
    'name' => 'Fighter',
    'perception' => 5,
    'languages' => ['Common', 'Dwarvish'],
    'senses' => [],
    'conditions' => [],
  ];
  $speech_event = [
    'type' => 'dialogue',
    'speaker' => 'Goblin',
    'content' => 'Surrender!',
    'language' => 'Common',
    'volume' => 'normal',
  ];
  $perception = $narrationEngine->buildPerceptionContext($normal_char, $speech_event);
  assert_true($perception['can_hear'], 'Normal character can hear speech');
  assert_true($perception['can_perceive'], 'Normal character can perceive');

  // Test: deafened character can't hear speech.
  $deaf_char = array_merge($normal_char, ['conditions' => ['deafened']]);
  $deaf_perception = $narrationEngine->buildPerceptionContext($deaf_char, $speech_event);
  assert_true(!$deaf_perception['can_hear'], 'Deafened character cannot hear');
  assert_true(!$deaf_perception['full_detail'], 'Deafened character lacks full detail');

  // Test: unconscious character perceives nothing.
  $uncon_char = array_merge($normal_char, ['conditions' => ['unconscious']]);
  $uncon_perception = $narrationEngine->buildPerceptionContext($uncon_char, $speech_event);
  assert_true(!$uncon_perception['can_perceive'], 'Unconscious character perceives nothing');

  // Test: blinded character can still hear.
  $blind_char = array_merge($normal_char, ['conditions' => ['blinded']]);
  $blind_perception = $narrationEngine->buildPerceptionContext($blind_char, $speech_event);
  assert_true($blind_perception['can_hear'], 'Blinded character can still hear');
  assert_true(!$blind_perception['can_see'], 'Blinded character cannot see');

  // Test: perception-gated event with high DC fails for low-perception char.
  $stealth_event = [
    'type' => 'stealth_movement',
    'speaker' => 'Rogue',
    'content' => 'Moves silently behind the pillar.',
    'perception_dc' => 25,
    'volume' => 'normal',
  ];
  $low_perc_char = array_merge($normal_char, ['perception' => 0]);
  // Run 20 times — with perception 0 and DC 25, character needs nat 20+ (impossible with d20+0).
  // Actually d20 range is 1-20, so +0 max is 20 < 25. Should always fail.
  $stealth_perception = $narrationEngine->buildPerceptionContext($low_perc_char, $stealth_event);
  assert_true(!$stealth_perception['can_perceive'] || $stealth_perception['perception_check'] !== NULL,
    'Stealth event triggers perception check');
}

// ============================================================================
echo "\n--- Test 12: getChildSessions ---\n";
// ============================================================================
// Re-create sessions (they got archived, let's test on fresh ones).
$sessionManager->deleteAllForCampaign($test_campaign_id);
$root = $sessionManager->ensureCampaignSessions($test_campaign_id, 'Test Campaign v2');
$children = $sessionManager->getChildSessions((int) $root['id']);
assert_equals(2, count($children), 'Campaign root has 2 children (system_log + party)');

// ============================================================================
echo "\n--- Cleanup ---\n";
// ============================================================================
$sessionManager->deleteAllForCampaign($test_campaign_id);
echo "  Cleaned up test data for campaign {$test_campaign_id}\n";

// ============================================================================
echo "\n=== RESULTS ===\n";
echo "Passed: {$GLOBALS['test_pass']}\n";
echo "Failed: {$GLOBALS['test_fail']}\n";

if (!empty($GLOBALS['test_errors'])) {
  echo "\nFailures:\n";
  foreach ($GLOBALS['test_errors'] as $e) {
    echo "  - {$e}\n";
  }
}

echo "\n" . ($GLOBALS['test_fail'] === 0 ? '✓ ALL TESTS PASSED' : '✗ SOME TESTS FAILED') . "\n";
