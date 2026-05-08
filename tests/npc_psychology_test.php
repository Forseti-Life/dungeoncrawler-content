<?php
/**
 * @file
 * Functional tests for NpcPsychologyService.
 *
 * Run with: drush php:script tests/npc_psychology_test.php
 */

use Drupal\dungeoncrawler_content\Service\NpcPsychologyService;

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

echo "=== NPC Psychology Service Tests ===\n\n";

/** @var NpcPsychologyService $service */
$service = \Drupal::service('dungeoncrawler_content.npc_psychology_service');
$db = \Drupal::database();

// Use a test campaign ID that won't conflict.
$test_campaign_id = 99999;

// Clean up any previous test data.
$db->delete('dc_npc_psychology')
  ->condition('campaign_id', $test_campaign_id)
  ->execute();

// ============================================================================
echo "--- Test 1: Create profile with seed data ---\n";
// ============================================================================
$profile = $service->getOrCreateProfile($test_campaign_id, 'goblin_guard_1', [
  'display_name' => 'Grukk the Guard',
  'creature_type' => 'goblin_warrior',
  'level' => 3,
  'description' => 'A scrawny goblin in dented chainmail, nervously clutching a rusty spear.',
  'stats' => [
    'currentHp' => 18,
    'maxHp' => 18,
    'ac' => 15,
    'perception' => 5,
    'fortitude' => 7,
    'reflex' => 9,
    'will' => 3,
  ],
  'role' => 'guard',
  'initial_attitude' => 'unfriendly',
  'abilities' => ['Goblin Scuttle', 'Spear Strike', 'Shield Block'],
  'equipment' => ['Rusty Spear', 'Dented Chain Shirt', 'Wooden Shield'],
  'languages' => ['Goblin', 'Common'],
  'senses' => ['Darkvision'],
]);

assert_true(!empty($profile), 'Profile created');
assert_equals('Grukk the Guard', $profile['display_name'], 'Display name set');
assert_equals('unfriendly', $profile['attitude'], 'Initial attitude from role');
assert_true(!empty($profile['personality_traits']), 'Personality traits generated');
assert_true(!empty($profile['motivations']), 'Motivations generated');
assert_true(!empty($profile['fears']), 'Fears generated');
assert_true(is_array($profile['character_sheet']), 'Character sheet is array');
assert_equals('Grukk the Guard', $profile['character_sheet']['display_name'], 'Sheet: display_name');
assert_equals(15, $profile['character_sheet']['stats']['ac'], 'Sheet: AC stored');
assert_true(in_array('Darkvision', $profile['character_sheet']['senses']), 'Sheet: senses');
assert_true(in_array('Goblin Scuttle', $profile['character_sheet']['abilities']), 'Sheet: abilities');

// Check personality axes are seeded for goblin type.
$axes = $profile['personality_axes'];
assert_true(is_array($axes), 'Personality axes is array');
assert_true(isset($axes['boldness']), 'Has boldness axis');
assert_true(isset($axes['cunning']), 'Has cunning axis');
assert_true($axes['cunning'] >= 6, 'Goblin has high cunning');
assert_true($axes['discipline'] <= 5, 'Goblin has low discipline');

echo "\n";

// ============================================================================
echo "--- Test 2: Load existing profile ---\n";
// ============================================================================
$loaded = $service->loadProfile($test_campaign_id, 'goblin_guard_1');
assert_true($loaded !== NULL, 'Profile loaded');
assert_equals('Grukk the Guard', $loaded['display_name'], 'Loaded name matches');
assert_equals('unfriendly', $loaded['attitude'], 'Loaded attitude matches');
assert_true(is_array($loaded['inner_monologue']), 'Inner monologue is array');
assert_true(is_array($loaded['attitude_history']), 'Attitude history is array');

echo "\n";

// ============================================================================
echo "--- Test 3: getOrCreateProfile returns existing ---\n";
// ============================================================================
$existing = $service->getOrCreateProfile($test_campaign_id, 'goblin_guard_1', [
  'display_name' => 'Should NOT Override',
]);
assert_equals('Grukk the Guard', $existing['display_name'], 'Existing profile not overwritten');

echo "\n";

// ============================================================================
echo "--- Test 4: Attitude shifting ---\n";
// ============================================================================
// unfriendly + shift +1 = indifferent
$result = $service->shiftAttitude('unfriendly', 1);
assert_equals('indifferent', $result, 'unfriendly +1 = indifferent');

// indifferent + shift +2 = helpful
$result = $service->shiftAttitude('indifferent', 2);
assert_equals('helpful', $result, 'indifferent +2 = helpful');

// friendly + shift -3 = hostile (clamped)
$result = $service->shiftAttitude('friendly', -3);
assert_equals('hostile', $result, 'friendly -3 = hostile (clamped)');

// helpful + shift +1 = helpful (clamped at top)
$result = $service->shiftAttitude('helpful', 1);
assert_equals('helpful', $result, 'helpful +1 = helpful (clamped top)');

// hostile + shift -1 = hostile (clamped at bottom)
$result = $service->shiftAttitude('hostile', -1);
assert_equals('hostile', $result, 'hostile -1 = hostile (clamped bottom)');

echo "\n";

// ============================================================================
echo "--- Test 5: Record inner monologue (diplomacy success) ---\n";
// ============================================================================
$entry = $service->recordInnerMonologue(
  $test_campaign_id,
  'goblin_guard_1',
  'diplomacy',
  'Player offered to spare the goblin in exchange for information',
  [
    'actor' => 'Thordak the Dwarf',
    'skill_check_result' => 'success',
    'severity' => 'moderate',
  ]
);

assert_true($entry !== NULL, 'Monologue entry created');
assert_true(!empty($entry['thought']), 'Thought generated');
assert_true(!empty($entry['emotion']), 'Emotion assigned');
assert_equals('diplomacy', $entry['event_type'], 'Event type recorded');

// Check that the profile's attitude shifted.
$updated = $service->loadProfile($test_campaign_id, 'goblin_guard_1');
$expected_shift = $entry['attitude_shift'];
echo "  (attitude_shift = {$expected_shift}, emotion = {$entry['emotion']})\n";
if ($expected_shift > 0) {
  assert_equals('indifferent', $updated['attitude'], 'Attitude shifted positively from unfriendly');
} else {
  assert_equals('unfriendly', $updated['attitude'], 'Attitude unchanged (shift was 0)');
}
assert_true(count($updated['inner_monologue']) >= 1, 'Monologue log has entry');

echo "\n";

// ============================================================================
echo "--- Test 6: Record inner monologue (intimidation) ---\n";
// ============================================================================
$entry2 = $service->recordInnerMonologue(
  $test_campaign_id,
  'goblin_guard_1',
  'intimidation',
  'Player shouted a battle cry and slammed their axe against the wall',
  [
    'actor' => 'Thordak the Dwarf',
    'severity' => 'moderate',
  ]
);

assert_true($entry2 !== NULL, 'Intimidation monologue created');
assert_true(in_array($entry2['emotion'], ['angry', 'fearful']), 'Emotion is angry or fearful');

echo "\n";

// ============================================================================
echo "--- Test 7: Record inner monologue (combat_outcome with mercy) ---\n";
// ============================================================================
$entry3 = $service->recordInnerMonologue(
  $test_campaign_id,
  'goblin_guard_1',
  'combat_outcome',
  'The adventurers spared the goblin after defeating its allies',
  [
    'severity' => 'major',
  ]
);

assert_true($entry3 !== NULL, 'Combat outcome monologue created');
assert_true($entry3['attitude_shift'] >= 1, 'Mercy causes positive attitude shift');
// AI may return different emotions for mercy — accept any positive/conflicted emotion.
$mercy_emotions = ['grateful', 'conflicted', 'hopeful', 'surprised'];
assert_true(in_array($entry3['emotion'], $mercy_emotions), 'Emotion is appropriate for mercy (got: ' . $entry3['emotion'] . ')');

echo "\n";

// ============================================================================
echo "--- Test 8: buildNpcContextForPrompt returns full sheet ---\n";
// ============================================================================
$context = $service->buildNpcContextForPrompt($test_campaign_id, 'goblin_guard_1', []);
assert_true(str_contains($context, 'Grukk the Guard'), 'Context includes NPC name');
assert_true(str_contains($context, 'CHARACTER SHEET'), 'Context includes CHARACTER SHEET header');
assert_true(str_contains($context, 'PERSONALITY & PSYCHOLOGY'), 'Context includes PSYCHOLOGY header');
assert_true(str_contains($context, 'Attitude toward party'), 'Context includes attitude');
assert_true(str_contains($context, 'Motivations'), 'Context includes motivations');
assert_true(str_contains($context, 'AC: 15'), 'Context includes AC stat');
assert_true(str_contains($context, 'Darkvision'), 'Context includes senses');
assert_true(str_contains($context, 'Goblin Scuttle'), 'Context includes abilities');
assert_true(str_contains($context, 'RECENT PRIVATE THOUGHTS'), 'Context includes recent thoughts');

echo "\n";

// ============================================================================
echo "--- Test 9: buildNpcContextForPrompt with live entity override ---\n";
// ============================================================================
$live_entity = [
  'state' => [
    'hit_points' => ['current' => 5, 'max' => 18],
    'metadata' => [
      'display_name' => 'Grukk the Guard',
      'stats' => ['ac' => 15],
    ],
  ],
];
$context_live = $service->buildNpcContextForPrompt($test_campaign_id, 'goblin_guard_1', $live_entity);
assert_true(str_contains($context_live, 'HP: 5/18'), 'Live HP overrides stored HP');

echo "\n";

// ============================================================================
echo "--- Test 10: Create second NPC and broadcast event ---\n";
// ============================================================================
$service->getOrCreateProfile($test_campaign_id, 'hobgoblin_captain_1', [
  'display_name' => 'Captain Kresh',
  'creature_type' => 'hobgoblin_soldier',
  'level' => 5,
  'role' => 'villain',
  'initial_attitude' => 'hostile',
]);

$results = $service->broadcastEventToNpcs(
  $test_campaign_id,
  ['goblin_guard_1', 'hobgoblin_captain_1'],
  'pc_action',
  'PC healed a wounded NPC before looting',
  ['actor' => 'Thordak', 'severity' => 'minor']
);

assert_equals(2, count($results), 'Both NPCs received event');
assert_true(isset($results['goblin_guard_1']), 'Goblin has monologue entry');
assert_true(isset($results['hobgoblin_captain_1']), 'Captain has monologue entry');

echo "\n";

// ============================================================================
echo "--- Test 11: ensureRoomNpcProfiles auto-creates missing profiles ---\n";
// ============================================================================
$room_entities = [
  [
    'entity_type' => 'npc',
    'entity_ref' => ['content_type' => 'npc', 'content_id' => 'merchant_dwarf_1'],
    'state' => [
      'metadata' => [
        'display_name' => 'Brokk the Merchant',
        'stats' => ['currentHp' => 30, 'maxHp' => 30, 'ac' => 12],
      ],
      'hit_points' => ['current' => 30, 'max' => 30],
    ],
    'role' => 'merchant',
  ],
  [
    'entity_type' => 'creature',
    'entity_ref' => ['content_type' => 'creature', 'content_id' => 'goblin_guard_1'],
    'state' => ['metadata' => ['display_name' => 'Grukk'], 'hit_points' => ['current' => 18, 'max' => 18]],
  ],
  [
    'entity_type' => 'item',
    'entity_ref' => ['content_type' => 'item', 'content_id' => 'chest_1'],
    'state' => [],
  ],
];

$created = $service->ensureRoomNpcProfiles($test_campaign_id, $room_entities);
assert_equals(1, $created, 'Only merchant created (goblin exists, item skipped)');

$merchant = $service->loadProfile($test_campaign_id, 'merchant_dwarf_1');
assert_true($merchant !== NULL, 'Merchant profile exists');
assert_equals('Brokk the Merchant', $merchant['display_name'], 'Merchant name correct');
assert_equals('indifferent', $merchant['attitude'], 'Merchant default attitude');

echo "\n";

// ============================================================================
echo "--- Test 12: getCampaignProfiles returns all ---\n";
// ============================================================================
$all = $service->getCampaignProfiles($test_campaign_id);
assert_true(count($all) >= 3, 'At least 3 profiles (goblin, captain, merchant)');

echo "\n";

// ============================================================================
echo "--- Test 13: getAttitude convenience method ---\n";
// ============================================================================
$attitude = $service->getAttitude($test_campaign_id, 'hobgoblin_captain_1');
assert_equals('hostile', $attitude, 'Captain attitude is hostile');

echo "\n";

// ============================================================================
echo "--- Test 14: Context length cap ---\n";
// ============================================================================
$context_full = $service->buildNpcContextForPrompt($test_campaign_id, 'goblin_guard_1');
assert_true(strlen($context_full) <= NpcPsychologyService::MAX_CONTEXT_LENGTH, 'Context within length cap');

echo "\n";

// ============================================================================
echo "--- Test 15: Non-existent profile returns minimal context ---\n";
// ============================================================================
$context_missing = $service->buildNpcContextForPrompt($test_campaign_id, 'nonexistent_npc_999');
assert_true(str_contains($context_missing, 'nonexistent_npc_999'), 'Fallback context mentions entity ref');
assert_true(str_contains($context_missing, 'No detailed background'), 'Fallback states no background');

echo "\n";

// ============================================================================
echo "--- Test 16: Monologue entry cap ---\n";
// ============================================================================
// Build 55 entries at once and update in a single DB write.
$profile_data = $service->loadProfile($test_campaign_id, 'goblin_guard_1');
$monologue = $profile_data['inner_monologue'] ?? [];
for ($i = 0; $i < 55; $i++) {
  $monologue[] = [
    'timestamp' => date('c'),
    'event_type' => 'observation',
    'event' => "Stress test event #{$i}",
    'thought' => "Thought #{$i}",
    'emotion' => 'neutral',
    'attitude_shift' => 0,
  ];
}
$service->updateProfile($test_campaign_id, 'goblin_guard_1', [
  'inner_monologue' => $monologue,
]);
// Now trigger a real recordInnerMonologue which should apply the cap.
$service->recordInnerMonologue(
  $test_campaign_id,
  'goblin_guard_1',
  'observation',
  'Cap trigger event',
  ['severity' => 'minor']
);
$capped = $service->loadProfile($test_campaign_id, 'goblin_guard_1');
assert_true(count($capped['inner_monologue']) <= NpcPsychologyService::MAX_MONOLOGUE_ENTRIES,
  'Monologue capped at ' . NpcPsychologyService::MAX_MONOLOGUE_ENTRIES);

echo "\n";

// ============================================================================
echo "--- Test 17: Update profile ---\n";
// ============================================================================
$updated = $service->updateProfile($test_campaign_id, 'hobgoblin_captain_1', [
  'motivations' => 'Reclaim honor after defeat',
  'bonds' => 'Former mentor of Grukk',
]);
assert_true($updated, 'Profile updated');
$captain = $service->loadProfile($test_campaign_id, 'hobgoblin_captain_1');
assert_equals('Reclaim honor after defeat', $captain['motivations'], 'Motivations updated');
assert_equals('Former mentor of Grukk', $captain['bonds'], 'Bonds updated');

echo "\n";

// ============================================================================
echo "--- Test 18: Betrayal event causes major negative shift ---\n";
// ============================================================================
// First reset merchant to friendly.
$service->updateProfile($test_campaign_id, 'merchant_dwarf_1', ['attitude' => 'friendly']);
$betrayal = $service->recordInnerMonologue(
  $test_campaign_id,
  'merchant_dwarf_1',
  'betrayal',
  'Player stole goods from the merchant while pretending to browse',
  ['actor' => 'Thordak', 'severity' => 'major']
);
assert_true($betrayal !== NULL, 'Betrayal monologue created');
assert_true($betrayal['attitude_shift'] <= -1, 'Betrayal causes negative shift');
assert_equals('angry', $betrayal['emotion'], 'Betrayal emotion is angry');

echo "\n";

// ============================================================================
// Cleanup.
// ============================================================================
$db->delete('dc_npc_psychology')
  ->condition('campaign_id', $test_campaign_id)
  ->execute();

echo "=== RESULTS ===\n";
$pass = $GLOBALS['test_pass'];
$fail = $GLOBALS['test_fail'];
$errors = $GLOBALS['test_errors'];
echo "Passed: {$pass}\n";
echo "Failed: {$fail}\n";
if ($errors) {
  echo "\nFailed tests:\n";
  foreach ($errors as $e) {
    echo "  - {$e}\n";
  }
}
echo "\n" . ($fail === 0 ? '✓ ALL TESTS PASSED' : '✗ SOME TESTS FAILED') . "\n";
