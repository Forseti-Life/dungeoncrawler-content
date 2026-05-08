<?php
/**
 * @file
 * Tests for campaign chat session bootstrapping during initialization.
 *
 * Run with: drush php:script tests/chat_bootstrap_test.php
 *
 * Validates that CampaignInitializationService::bootstrapChatSessions()
 * creates the full session hierarchy—including dungeon and room sessions—
 * for a newly-created campaign.
 */

use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\CampaignInitializationService;

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

function assert_contains($needle, $haystack, $label) {
  assert_true(str_contains((string) $haystack, (string) $needle), $label);
}

echo "=== Campaign Chat Bootstrap Tests ===\n\n";

/** @var ChatSessionManager $sessionManager */
$sessionManager = \Drupal::service('dungeoncrawler_content.chat_session_manager');

/** @var CampaignInitializationService $initService */
$initService = \Drupal::service('dungeoncrawler_content.campaign_initialization');

$db = \Drupal::database();

// Use a specific test user ID.
$test_uid = (int) \Drupal::currentUser()->id();
if ($test_uid < 1) {
  $test_uid = 1;
}

// ============================================================================
echo "--- Test 1: Full campaign initialization creates all chat sessions ---\n";
// ============================================================================

$campaign_id = $initService->initializeCampaign(
  $test_uid,
  'Chat Bootstrap Test Campaign',
  'classic_dungeon',
  'normal'
);

assert_true($campaign_id > 0, 'Campaign was created successfully');

if ($campaign_id > 0) {
  // 1a: Campaign root session exists.
  $root_key = $sessionManager->campaignSessionKey($campaign_id);
  $root = $sessionManager->loadSession($root_key);
  assert_not_null($root, 'Campaign root session exists');
  assert_equals('campaign', $root['session_type'] ?? '', 'Root session type is campaign');

  // 1b: System log session exists.
  $syslog_key = $sessionManager->systemLogSessionKey($campaign_id);
  $syslog = $sessionManager->loadSession($syslog_key);
  assert_not_null($syslog, 'System log session exists');
  assert_equals('system_log', $syslog['session_type'] ?? '', 'System log session type correct');

  // 1c: Party session exists.
  $party_key = $sessionManager->partySessionKey($campaign_id);
  $party = $sessionManager->loadSession($party_key);
  assert_not_null($party, 'Party session exists');
  assert_equals('party', $party['session_type'] ?? '', 'Party session type correct');

  // 1d: Dungeon session exists for the starter dungeon.
  // The dungeon_id should be in dc_campaign_dungeons.
  $dungeon_row = $db->select('dc_campaign_dungeons', 'd')
    ->fields('d', ['dungeon_id'])
    ->condition('campaign_id', $campaign_id)
    ->orderBy('id', 'DESC')
    ->range(0, 1)
    ->execute()
    ->fetchAssoc();
  assert_not_null($dungeon_row, 'Starter dungeon row exists');

  $dungeon_id = $dungeon_row['dungeon_id'] ?? '';
  assert_true($dungeon_id !== '', 'Starter dungeon has non-empty ID');

  $dungeon_key = $sessionManager->dungeonSessionKey($campaign_id, $dungeon_id);
  $dungeon_session = $sessionManager->loadSession($dungeon_key);
  assert_not_null($dungeon_session, 'Dungeon chat session exists');
  assert_equals('dungeon', $dungeon_session['session_type'] ?? '', 'Dungeon session type correct');

  // 1e: Room session exists for tavern_entrance.
  $room_key = $sessionManager->roomSessionKey($campaign_id, $dungeon_id, 'tavern_entrance');
  $room_session = $sessionManager->loadSession($room_key);
  assert_not_null($room_session, 'Room chat session exists for tavern_entrance');
  assert_equals('room', $room_session['session_type'] ?? '', 'Room session type correct');

  // 1f: Room session is a child of the dungeon session.
  if ($dungeon_session && $room_session) {
    assert_equals(
      (int) $dungeon_session['id'],
      (int) $room_session['parent_session_id'],
      'Room session is child of dungeon session'
    );
  }

  // 1g: Dungeon session is a child of campaign root.
  if ($root && $dungeon_session) {
    assert_equals(
      (int) $root['id'],
      (int) $dungeon_session['parent_session_id'],
      'Dungeon session is child of campaign root'
    );
  }
}

// ============================================================================
echo "\n--- Test 2: Room session has initial welcome message ---\n";
// ============================================================================
if ($campaign_id > 0 && isset($room_session)) {
  $messages = $sessionManager->getMessages((int) $room_session['id'], 10);
  assert_greater_than(0, count($messages), 'Room session has at least 1 message');

  if (!empty($messages)) {
    $first_msg = $messages[0];
    assert_equals('Game Master', $first_msg['speaker'] ?? '', 'Welcome message speaker is Game Master');
    assert_equals('narrative', $first_msg['message_type'] ?? '', 'Welcome message type is narrative');
    assert_contains('Tavern Entrance', $first_msg['message'] ?? '', 'Welcome message mentions Tavern Entrance');
  }
}

// ============================================================================
echo "\n--- Test 3: System log has initialization message ---\n";
// ============================================================================
if ($campaign_id > 0 && isset($syslog)) {
  $messages = $sessionManager->getMessages((int) $syslog['id'], 10);
  assert_greater_than(0, count($messages), 'System log has at least 1 message');

  if (!empty($messages)) {
    $first_msg = $messages[0];
    assert_equals('System', $first_msg['speaker'] ?? '', 'System log message speaker is System');
    assert_contains('Dice log ready', $first_msg['message'] ?? '', 'System log message mentions dice log');
  }
}

// ============================================================================
echo "\n--- Test 4: Campaign root has GM init message ---\n";
// ============================================================================
if ($campaign_id > 0 && isset($root)) {
  $messages = $sessionManager->getMessages((int) $root['id'], 10);
  assert_greater_than(0, count($messages), 'Campaign root has at least 1 message');

  // Root should have the GM system init message + any feed-ups from room.
  $gm_msg = NULL;
  foreach ($messages as $m) {
    if (($m['speaker'] ?? '') === 'System' && str_contains($m['message'] ?? '', 'GM master feed active')) {
      $gm_msg = $m;
      break;
    }
  }
  assert_not_null($gm_msg, 'Campaign root contains GM init message');
}

// ============================================================================
echo "\n--- Test 5: Session count is correct (min 5) ---\n";
// ============================================================================
if ($campaign_id > 0) {
  $session_count = (int) $db->select('dc_chat_sessions', 's')
    ->condition('campaign_id', $campaign_id)
    ->countQuery()
    ->execute()
    ->fetchField();
  // We expect at least: root, system_log, party, dungeon, room = 5
  assert_true($session_count >= 5, "Campaign has at least 5 sessions (got: {$session_count})");
}

// ============================================================================
echo "\n--- Test 6: Seed dungeon_data has no stale chat ---\n";
// ============================================================================
if ($campaign_id > 0) {
  $dungeon_data_raw = $db->select('dc_campaign_dungeons', 'd')
    ->fields('d', ['dungeon_data'])
    ->condition('campaign_id', $campaign_id)
    ->orderBy('id', 'DESC')
    ->range(0, 1)
    ->execute()
    ->fetchField();

  $dungeon_data = json_decode($dungeon_data_raw ?: '{}', TRUE);
  $has_stale_chat = FALSE;
  foreach (($dungeon_data['rooms'] ?? []) as $room) {
    if (!empty($room['chat'])) {
      $has_stale_chat = TRUE;
      break;
    }
  }
  assert_true(!$has_stale_chat, 'Seed dungeon_data has no stale pre-populated chat messages');
}

// ============================================================================
echo "\n--- Test 7: Second campaign gets independent sessions ---\n";
// ============================================================================
$campaign_id_2 = $initService->initializeCampaign(
  $test_uid,
  'Chat Bootstrap Test Campaign 2',
  'classic_dungeon',
  'normal'
);
assert_true($campaign_id_2 > 0, 'Second campaign created');
assert_true($campaign_id_2 !== $campaign_id, 'Second campaign has different ID');

if ($campaign_id_2 > 0) {
  $root_2 = $sessionManager->loadSession($sessionManager->campaignSessionKey($campaign_id_2));
  assert_not_null($root_2, 'Second campaign root session exists');

  if ($root && $root_2) {
    assert_true(
      (int) $root['id'] !== (int) $root_2['id'],
      'Second campaign root session is distinct from first'
    );
  }

  $party_2 = $sessionManager->loadSession($sessionManager->partySessionKey($campaign_id_2));
  assert_not_null($party_2, 'Second campaign party session exists');

  if ($party && $party_2) {
    assert_true(
      (int) $party['id'] !== (int) $party_2['id'],
      'Second campaign party session is distinct from first'
    );
  }
}

// ============================================================================
echo "\n--- Test 8: ChatSessionApi endpoints return data for new campaign ---\n";
// ============================================================================
if ($campaign_id > 0) {
  // System log endpoint
  $sys_messages = $sessionManager->getMessages((int) $syslog['id'], 10);
  assert_true(count($sys_messages) > 0, 'System log endpoint would return messages');

  // Party chat endpoint
  $party_messages = $sessionManager->getMessages((int) $party['id'], 10);
  // Party starts empty (no autopost), which is normal
  assert_true(is_array($party_messages), 'Party chat returns valid array');
}

// ============================================================================
echo "\n--- Cleanup ---\n";
// ============================================================================

// Delete test sessions.
if ($campaign_id > 0) {
  $sessionManager->deleteAllForCampaign($campaign_id);
  $db->delete('dc_campaigns')->condition('id', $campaign_id)->execute();
  $db->delete('dc_campaign_dungeons')->condition('campaign_id', $campaign_id)->execute();
  $db->delete('dc_campaign_rooms')->condition('campaign_id', $campaign_id)->execute();
  $db->delete('dc_campaign_room_states')->condition('campaign_id', $campaign_id)->execute();
  $db->delete('dc_campaign_content_registry')->condition('campaign_id', $campaign_id)->execute();
  $db->delete('dc_campaign_characters')->condition('campaign_id', $campaign_id)->execute();
  if ($db->schema()->tableExists('dungeoncrawler_content_quest_templates')) {
    // Quest templates aren't campaign-scoped, skip.
  }
  if ($db->schema()->tableExists('dc_campaign_quests')) {
    $db->delete('dc_campaign_quests')->condition('campaign_id', $campaign_id)->execute();
  }
  echo "  Cleaned up test campaign {$campaign_id}\n";
}

if (isset($campaign_id_2) && $campaign_id_2 > 0) {
  $sessionManager->deleteAllForCampaign($campaign_id_2);
  $db->delete('dc_campaigns')->condition('id', $campaign_id_2)->execute();
  $db->delete('dc_campaign_dungeons')->condition('campaign_id', $campaign_id_2)->execute();
  $db->delete('dc_campaign_rooms')->condition('campaign_id', $campaign_id_2)->execute();
  $db->delete('dc_campaign_room_states')->condition('campaign_id', $campaign_id_2)->execute();
  $db->delete('dc_campaign_content_registry')->condition('campaign_id', $campaign_id_2)->execute();
  $db->delete('dc_campaign_characters')->condition('campaign_id', $campaign_id_2)->execute();
  if ($db->schema()->tableExists('dc_campaign_quests')) {
    $db->delete('dc_campaign_quests')->condition('campaign_id', $campaign_id_2)->execute();
  }
  echo "  Cleaned up test campaign {$campaign_id_2}\n";
}

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
