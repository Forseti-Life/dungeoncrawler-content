<?php
/**
 * @file
 * Functional tests for CombatApiController — all 12 endpoints.
 *
 * Run with: drush php:script web/modules/custom/dungeoncrawler_content/tests/combat_api_controller_test.php
 *
 * Tests:
 *   - HP update (damage + healing)
 *   - Temporary HP application
 *   - Condition apply / remove / list
 *   - Initiative order + reroll
 *   - Participant add / remove / update
 *   - Combat log retrieval (paginated + filtered)
 *   - Combat statistics aggregation
 */

use Drupal\dungeoncrawler_content\Controller\CombatApiController;
use Symfony\Component\HttpFoundation\Request;

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

function assert_greater_than($threshold, $actual, $label) {
  assert_true($actual > $threshold, "{$label} (expected > {$threshold}, got: {$actual})");
}

function assert_array_key($key, $array, $label) {
  assert_true(array_key_exists($key, $array), $label);
}

echo "=== CombatApiController Tests ===\n\n";

$db = \Drupal::database();

// High ID range for test isolation.
$test_encounter_id = 99100;
$now = time();

// ---- Helpers ----

function create_test_encounter($db, $encounter_id, $round = 1, $turn = 0) {
  $db->merge('combat_encounters')
    ->key('id', $encounter_id)
    ->fields([
      'campaign_id' => 99100,
      'status' => 'active',
      'current_round' => $round,
      'turn_index' => $turn,
      'created' => time(),
      'updated' => time(),
    ])
    ->execute();
}

function create_participant($db, $encounter_id, $name, $hp, $max_hp, $options = []) {
  return (int) $db->insert('combat_participants')
    ->fields([
      'encounter_id' => $encounter_id,
      'entity_id' => rand(90000, 99999),
      'entity_ref' => $options['entity_ref'] ?? NULL,
      'name' => $name,
      'team' => $options['team'] ?? 'player',
      'initiative' => $options['initiative'] ?? 10,
      'initiative_roll' => $options['initiative_roll'] ?? NULL,
      'ac' => $options['ac'] ?? 15,
      'hp' => $hp,
      'max_hp' => $max_hp,
      'actions_remaining' => $options['actions_remaining'] ?? 3,
      'attacks_this_turn' => 0,
      'reaction_available' => 1,
      'position_q' => $options['position_q'] ?? NULL,
      'position_r' => $options['position_r'] ?? NULL,
      'is_defeated' => 0,
      'created' => time(),
      'updated' => time(),
    ])
    ->execute();
}

function load_participant($db, $id) {
  return $db->select('combat_participants', 'p')
    ->fields('p')
    ->condition('id', $id)
    ->execute()
    ->fetchAssoc();
}

function cleanup($db, $encounter_id) {
  $db->delete('combat_actions')
    ->condition('encounter_id', $encounter_id)
    ->execute();
  $db->delete('combat_damage_log')
    ->condition('encounter_id', $encounter_id)
    ->execute();
  $db->delete('combat_conditions')
    ->condition('encounter_id', $encounter_id)
    ->execute();
  $db->delete('combat_participants')
    ->condition('encounter_id', $encounter_id)
    ->execute();
  $db->delete('combat_encounters')
    ->condition('id', $encounter_id)
    ->execute();
}

function make_request($body) {
  return Request::create('/', 'POST', [], [], [], [], json_encode($body));
}

function make_get_request($query = []) {
  $qs = http_build_query($query);
  return Request::create('/?' . $qs, 'GET');
}

function decode_response($response) {
  return json_decode($response->getContent(), TRUE);
}

// Clean slate.
cleanup($db, $test_encounter_id);
create_test_encounter($db, $test_encounter_id);

// Build controller via container.
$controller = CombatApiController::create(\Drupal::getContainer());

// Create baseline participants.
$fighter_id = create_participant($db, $test_encounter_id, 'Fighter', 50, 50, [
  'initiative' => 18, 'team' => 'player', 'ac' => 20,
]);
$wizard_id = create_participant($db, $test_encounter_id, 'Wizard', 30, 30, [
  'initiative' => 14, 'team' => 'player', 'ac' => 14,
]);
$goblin_id = create_participant($db, $test_encounter_id, 'Goblin', 20, 20, [
  'initiative' => 12, 'team' => 'enemy', 'ac' => 16,
]);

// ============================================================================
echo "--- Test 1: updateHP — damage ---\n";
// ============================================================================
$req = make_request(['change_type' => 'damage', 'amount' => 10, 'damage_type' => 'slashing', 'source' => 'goblin_sword']);
$res = decode_response($controller->updateHP($test_encounter_id, $fighter_id, $req));

assert_equals((int) $fighter_id, $res['participant_id'], 'updateHP damage: participant_id matches');
assert_equals(40, $res['hp_after'], 'updateHP damage: hp_after = 40 (50 - 10)');
assert_equals(0, $res['temp_hp_used'], 'updateHP damage: no temp HP used');
assert_equals('active', $res['new_status'], 'updateHP damage: status remains active');

$row = load_participant($db, $fighter_id);
assert_equals('40', $row['hp'], 'updateHP damage: DB hp = 40');

// ============================================================================
echo "\n--- Test 2: updateHP — healing ---\n";
// ============================================================================
$req = make_request(['change_type' => 'healing', 'amount' => 5, 'source' => 'heal_spell']);
$res = decode_response($controller->updateHP($test_encounter_id, $fighter_id, $req));

assert_equals(45, $res['hp_after'], 'updateHP heal: hp_after = 45 (40 + 5)');
assert_equals(5, $res['healing_applied'], 'updateHP heal: healing_applied = 5');

$row = load_participant($db, $fighter_id);
assert_equals('45', $row['hp'], 'updateHP heal: DB hp = 45');

// ============================================================================
echo "\n--- Test 3: updateHP — invalid body ---\n";
// ============================================================================
$bad_req = Request::create('/', 'POST', [], [], [], [], '');
$res = decode_response($controller->updateHP($test_encounter_id, $fighter_id, $bad_req));
assert_true(isset($res['error']), 'updateHP bad body: returns error');

// ============================================================================
echo "\n--- Test 4: updateHP — zero amount ---\n";
// ============================================================================
$req = make_request(['change_type' => 'damage', 'amount' => 0]);
$res = decode_response($controller->updateHP($test_encounter_id, $fighter_id, $req));
assert_true(isset($res['error']), 'updateHP zero amount: returns error');

// ============================================================================
echo "\n--- Test 5: applyTempHP ---\n";
// ============================================================================
$req = make_request(['amount' => 10, 'source' => 'false_life']);
$res = decode_response($controller->applyTempHP($test_encounter_id, $fighter_id, $req));

assert_equals(10, $res['temp_hp_after'], 'applyTempHP: temp_hp_after = 10');
assert_true(strpos($res['message'], 'Gained') !== FALSE, 'applyTempHP: message says Gained');

// ============================================================================
echo "\n--- Test 6: applyTempHP — lower value does not replace ---\n";
// ============================================================================
$req = make_request(['amount' => 5, 'source' => 'lesser_false_life']);
$res = decode_response($controller->applyTempHP($test_encounter_id, $fighter_id, $req));

assert_equals(10, $res['temp_hp_after'], 'applyTempHP lower: keeps existing 10');
assert_true(strpos($res['message'], 'Kept') !== FALSE, 'applyTempHP lower: message says Kept');

// ============================================================================
echo "\n--- Test 7: applyTempHP — invalid ---\n";
// ============================================================================
$req = make_request(['amount' => -1]);
$res = decode_response($controller->applyTempHP($test_encounter_id, $fighter_id, $req));
assert_true(isset($res['error']), 'applyTempHP negative: returns error');

// ============================================================================
echo "\n--- Test 8: damage with temp HP absorb ---\n";
// ============================================================================
$req = make_request(['change_type' => 'damage', 'amount' => 15, 'damage_type' => 'fire']);
$res = decode_response($controller->updateHP($test_encounter_id, $fighter_id, $req));

assert_equals(10, $res['temp_hp_used'], 'damage+tempHP: 10 temp HP absorbed');
assert_equals(40, $res['hp_after'], 'damage+tempHP: hp_after = 40 (45 - 5 real damage)');

// ============================================================================
echo "\n--- Test 9: applyCondition ---\n";
// ============================================================================
$req = make_request([
  'condition_type' => 'frightened',
  'value' => 2,
  'source' => 'dragon_breath',
  'duration_type' => 'encounter',
]);
$response = $controller->applyCondition($test_encounter_id, $goblin_id, $req);
$res = decode_response($response);

assert_equals(201, $response->getStatusCode(), 'applyCondition: status 201');
assert_equals('frightened', $res['condition_type'], 'applyCondition: condition_type = frightened');
assert_equals(2, $res['value'], 'applyCondition: value = 2');
assert_true($res['condition_id'] > 0, 'applyCondition: got condition_id > 0');

$saved_condition_id = $res['condition_id'];

// ============================================================================
echo "\n--- Test 10: applyCondition — missing type ---\n";
// ============================================================================
$req = make_request(['value' => 1]);
$response = $controller->applyCondition($test_encounter_id, $goblin_id, $req);
$res = decode_response($response);

assert_equals(400, $response->getStatusCode(), 'applyCondition missing type: status 400');

// ============================================================================
echo "\n--- Test 11: listConditions ---\n";
// ============================================================================
$res = decode_response($controller->listConditions($test_encounter_id, $goblin_id));

assert_equals((int) $goblin_id, $res['participant_id'], 'listConditions: participant_id matches');
assert_true(count($res['conditions']) >= 1, 'listConditions: has >= 1 condition');

$found_frightened = FALSE;
foreach ($res['conditions'] as $c) {
  if ($c['condition_type'] === 'frightened') {
    $found_frightened = TRUE;
    assert_equals(2, $c['value'], 'listConditions: frightened value = 2');
  }
}
assert_true($found_frightened, 'listConditions: found frightened condition');

// ============================================================================
echo "\n--- Test 12: removeCondition ---\n";
// ============================================================================
$res = decode_response($controller->removeCondition($test_encounter_id, $goblin_id, $saved_condition_id));

assert_equals(TRUE, $res['removed'], 'removeCondition: removed = TRUE');
assert_equals((int) $saved_condition_id, $res['condition_id'], 'removeCondition: condition_id matches');

// Verify it's gone.
$res = decode_response($controller->listConditions($test_encounter_id, $goblin_id));
$still_has = FALSE;
foreach ($res['conditions'] as $c) {
  if ((int) ($c['condition_id'] ?? 0) === (int) $saved_condition_id) {
    $still_has = TRUE;
  }
}
assert_true(!$still_has, 'removeCondition: condition no longer in active list');

// ============================================================================
echo "\n--- Test 13: getInitiative ---\n";
// ============================================================================
$res = decode_response($controller->getInitiative($test_encounter_id));

assert_equals((int) $test_encounter_id, $res['encounter_id'], 'getInitiative: encounter_id matches');
assert_equals(1, $res['current_round'], 'getInitiative: current_round = 1');
assert_true(count($res['initiative_order']) >= 3, 'getInitiative: has >= 3 participants');

// Should be sorted by initiative DESC (Fighter 18, Wizard 14, Goblin 12).
$first = $res['initiative_order'][0];
assert_equals(18, $first['initiative'], 'getInitiative: first in order has init=18 (Fighter)');
assert_equals('Fighter', $first['name'], 'getInitiative: first participant is Fighter');

// Check turn marker.
$has_current = FALSE;
foreach ($res['initiative_order'] as $p) {
  if ($p['is_current_turn']) {
    $has_current = TRUE;
  }
}
assert_true($has_current, 'getInitiative: one participant has is_current_turn = TRUE');

// ============================================================================
echo "\n--- Test 14: getInitiative — conditions included ---\n";
// ============================================================================
// Apply a condition to goblin so it shows in initiative.
$req = make_request([
  'condition_type' => 'flat_footed',
  'value' => 1,
  'source' => 'flank',
]);
$controller->applyCondition($test_encounter_id, $goblin_id, $req);

$res = decode_response($controller->getInitiative($test_encounter_id));
$goblin_entry = NULL;
foreach ($res['initiative_order'] as $p) {
  if ($p['name'] === 'Goblin') {
    $goblin_entry = $p;
  }
}
assert_true($goblin_entry !== NULL, 'getInitiative conditions: found Goblin');
assert_true(count($goblin_entry['conditions']) >= 1, 'getInitiative conditions: Goblin has conditions');

// ============================================================================
echo "\n--- Test 15: getInitiative — nonexistent encounter ---\n";
// ============================================================================
$response = $controller->getInitiative(99999);
$res = decode_response($response);
assert_equals(404, $response->getStatusCode(), 'getInitiative missing: status 404');

// ============================================================================
echo "\n--- Test 16: rerollInitiative ---\n";
// ============================================================================
$old_goblin_init = 12;
$req = make_request(['participant_ids' => [$goblin_id]]);
$res = decode_response($controller->rerollInitiative($test_encounter_id, $req));

assert_true(count($res['rerolled']) === 1, 'rerollInitiative: rerolled 1 participant');
assert_equals((int) $goblin_id, $res['rerolled'][0]['participant_id'], 'rerollInitiative: correct participant');
assert_true(is_int($res['rerolled'][0]['new_initiative']), 'rerollInitiative: new_initiative is int');
assert_true($res['rerolled'][0]['new_initiative'] >= 1 && $res['rerolled'][0]['new_initiative'] <= 20, 'rerollInitiative: new_initiative in d20 range');
assert_true(count($res['new_initiative_order']) >= 3, 'rerollInitiative: full order returned');

// ============================================================================
echo "\n--- Test 17: rerollInitiative — empty list ---\n";
// ============================================================================
$req = make_request([]);
$response = $controller->rerollInitiative($test_encounter_id, $req);
$res = decode_response($response);
assert_equals(400, $response->getStatusCode(), 'rerollInitiative empty: status 400');

// ============================================================================
echo "\n--- Test 18: addParticipant ---\n";
// ============================================================================
$req = make_request([
  'name' => 'Orc Warrior',
  'team' => 'enemy',
  'entity_id' => 91000,
  'hp' => 25,
  'max_hp' => 25,
  'ac' => 17,
  'roll_initiative' => TRUE,
]);
$response = $controller->addParticipant($test_encounter_id, $req);
$res = decode_response($response);

assert_equals(201, $response->getStatusCode(), 'addParticipant: status 201');
assert_equals('Orc Warrior', $res['name'], 'addParticipant: name = Orc Warrior');
assert_equals('enemy', $res['team'], 'addParticipant: team = enemy');
assert_true($res['participant_id'] > 0, 'addParticipant: got participant_id > 0');
assert_true($res['initiative'] >= 1 && $res['initiative'] <= 20, 'addParticipant: initiative rolled in d20 range');

$orc_id = $res['participant_id'];

// Verify in DB.
$orc_row = load_participant($db, $orc_id);
assert_equals('25', $orc_row['hp'], 'addParticipant DB: hp = 25');
assert_equals('17', $orc_row['ac'], 'addParticipant DB: ac = 17');
assert_equals('enemy', $orc_row['team'], 'addParticipant DB: team = enemy');

// ============================================================================
echo "\n--- Test 19: addParticipant — without initiative roll ---\n";
// ============================================================================
$req = make_request([
  'name' => 'Skeleton',
  'team' => 'enemy',
  'hp' => 15,
  'max_hp' => 15,
  'initiative' => 8,
]);
$response = $controller->addParticipant($test_encounter_id, $req);
$res = decode_response($response);

assert_equals(201, $response->getStatusCode(), 'addParticipant manual init: status 201');
assert_equals(8, $res['initiative'], 'addParticipant manual init: initiative = 8');

$skeleton_id = $res['participant_id'];

// ============================================================================
echo "\n--- Test 20: addParticipant — missing name ---\n";
// ============================================================================
$req = make_request(['team' => 'enemy', 'hp' => 10, 'max_hp' => 10]);
$response = $controller->addParticipant($test_encounter_id, $req);
assert_equals(400, $response->getStatusCode(), 'addParticipant no name: status 400');

// ============================================================================
echo "\n--- Test 21: addParticipant — nonexistent encounter ---\n";
// ============================================================================
$req = make_request(['name' => 'Ghost', 'team' => 'enemy']);
$response = $controller->addParticipant(99999, $req);
assert_equals(404, $response->getStatusCode(), 'addParticipant bad encounter: status 404');

// ============================================================================
echo "\n--- Test 22: updateParticipant ---\n";
// ============================================================================
$req = make_request([
  'ac' => 22,
  'position_q' => 5,
  'position_r' => 3,
]);
$res = decode_response($controller->updateParticipant($test_encounter_id, $fighter_id, $req));

assert_true(in_array('ac', $res['updated_fields']), 'updateParticipant: ac in updated_fields');
assert_true(in_array('position_q', $res['updated_fields']), 'updateParticipant: position_q in updated_fields');
assert_true(in_array('position_r', $res['updated_fields']), 'updateParticipant: position_r in updated_fields');

$row = load_participant($db, $fighter_id);
assert_equals('22', $row['ac'], 'updateParticipant DB: ac = 22');
assert_equals('5', $row['position_q'], 'updateParticipant DB: position_q = 5');

// ============================================================================
echo "\n--- Test 23: updateParticipant — no valid fields ---\n";
// ============================================================================
$req = make_request(['forbidden_field' => 'hack']);
$response = $controller->updateParticipant($test_encounter_id, $fighter_id, $req);
assert_equals(400, $response->getStatusCode(), 'updateParticipant bad field: status 400');

// ============================================================================
echo "\n--- Test 24: updateParticipant — nonexistent participant ---\n";
// ============================================================================
$req = make_request(['ac' => 10]);
$response = $controller->updateParticipant($test_encounter_id, 99999, $req);
assert_equals(404, $response->getStatusCode(), 'updateParticipant missing: status 404');

// ============================================================================
echo "\n--- Test 25: removeParticipant ---\n";
// ============================================================================
$req = make_request(['reason' => 'fled']);
$res = decode_response($controller->removeParticipant($test_encounter_id, $skeleton_id, $req));

assert_equals(TRUE, $res['removed'], 'removeParticipant: removed = TRUE');
assert_equals('fled', $res['reason'], 'removeParticipant: reason = fled');

$row = load_participant($db, $skeleton_id);
assert_equals('1', $row['is_defeated'], 'removeParticipant DB: is_defeated = 1');

// ============================================================================
echo "\n--- Test 26: removeParticipant — nonexistent ---\n";
// ============================================================================
$req = make_request(['reason' => 'test']);
$response = $controller->removeParticipant($test_encounter_id, 99999, $req);
assert_equals(404, $response->getStatusCode(), 'removeParticipant missing: status 404');

// ============================================================================
echo "\n--- Test 27: removeParticipant — bad encounter ---\n";
// ============================================================================
$req = make_request(['reason' => 'test']);
$response = $controller->removeParticipant(99999, $fighter_id, $req);
assert_equals(404, $response->getStatusCode(), 'removeParticipant bad encounter: status 404');

// ============================================================================
echo "\n--- Test 28: getLog — with action entries ---\n";
// ============================================================================
// The addParticipant and removeParticipant calls logged actions. Query them.
$req = make_get_request(['per_page' => 10]);
$res = decode_response($controller->getLog($test_encounter_id, $req));

assert_equals((int) $test_encounter_id, $res['encounter_id'], 'getLog: encounter_id matches');
assert_true(is_array($res['log_entries']), 'getLog: log_entries is array');
assert_true($res['meta']['total'] >= 2, 'getLog: total >= 2 (join + removed actions)');
assert_true($res['meta']['per_page'] === 10, 'getLog: per_page = 10');

// Check first entry has expected keys.
if (count($res['log_entries']) > 0) {
  $entry = $res['log_entries'][0];
  assert_array_key('action_id', $entry, 'getLog entry: has action_id');
  assert_array_key('participant_id', $entry, 'getLog entry: has participant_id');
  assert_array_key('action_type', $entry, 'getLog entry: has action_type');
  assert_array_key('payload', $entry, 'getLog entry: has payload');
}

// ============================================================================
echo "\n--- Test 29: getLog — filtered by action_type ---\n";
// ============================================================================
$req = make_get_request(['action_type' => 'join']);
$res = decode_response($controller->getLog($test_encounter_id, $req));

foreach ($res['log_entries'] as $entry) {
  assert_equals('join', $entry['action_type'], 'getLog filtered: all entries are join type');
}
assert_true($res['meta']['total'] >= 1, 'getLog filtered: at least 1 join entry');

// ============================================================================
echo "\n--- Test 30: getLog — filtered by participant_id ---\n";
// ============================================================================
$req = make_get_request(['participant_id' => $orc_id]);
$res = decode_response($controller->getLog($test_encounter_id, $req));

foreach ($res['log_entries'] as $entry) {
  assert_equals((int) $orc_id, $entry['participant_id'], 'getLog participant filter: matches orc_id');
}

// ============================================================================
echo "\n--- Test 31: getLog — pagination ---\n";
// ============================================================================
$req = make_get_request(['per_page' => 1, 'page' => 1]);
$res = decode_response($controller->getLog($test_encounter_id, $req));

assert_equals(1, $res['meta']['per_page'], 'getLog page1: per_page = 1');
assert_equals(1, $res['meta']['page'], 'getLog page1: page = 1');
assert_true(count($res['log_entries']) <= 1, 'getLog page1: at most 1 entry');

$req = make_get_request(['per_page' => 1, 'page' => 2]);
$res2 = decode_response($controller->getLog($test_encounter_id, $req));
assert_equals(2, $res2['meta']['page'], 'getLog page2: page = 2');

// ============================================================================
echo "\n--- Test 32: getStatistics ---\n";
// ============================================================================
// Log some damage entries so statistics have data.
$encounterStore = \Drupal::service('dungeoncrawler_content.combat_encounter_store');
$encounterStore->logDamage([
  'encounter_id' => $test_encounter_id,
  'participant_id' => $fighter_id,
  'amount' => 15,
  'damage_type' => 'fire',
  'source' => 'dragon_breath',
  'hp_before' => 40,
  'hp_after' => 25,
]);
$encounterStore->logDamage([
  'encounter_id' => $test_encounter_id,
  'participant_id' => $goblin_id,
  'amount' => 8,
  'damage_type' => 'slashing',
  'source' => 'longsword',
  'hp_before' => 20,
  'hp_after' => 12,
]);

// Log an attack action.
$encounterStore->logAction([
  'encounter_id' => $test_encounter_id,
  'participant_id' => $fighter_id,
  'action_type' => 'attack',
  'target_id' => $goblin_id,
  'payload' => json_encode(['weapon' => 'longsword']),
  'result' => json_encode(['hit' => TRUE, 'damage' => 8]),
]);

$res = decode_response($controller->getStatistics($test_encounter_id));

assert_equals((int) $test_encounter_id, $res['encounter_id'], 'getStatistics: encounter_id matches');
assert_equals(1, $res['rounds_elapsed'], 'getStatistics: rounds_elapsed = 1');
assert_true($res['total_actions'] >= 3, 'getStatistics: total_actions >= 3');
assert_true(is_array($res['actions_by_type']), 'getStatistics: actions_by_type is array');
assert_true(isset($res['actions_by_type']['join']), 'getStatistics: has join action type');
assert_true(isset($res['actions_by_type']['attack']), 'getStatistics: has attack action type');

// Damage stats — HPManager::applyDamage also logs to combat_damage_log,
// so totals include both the updateHP calls earlier AND these explicit logs.
assert_true($res['damage_statistics']['total_damage'] >= 23, 'getStatistics: total_damage >= 23');
assert_true(isset($res['damage_statistics']['damage_by_type']['fire']), 'getStatistics: has fire damage type');
assert_true($res['damage_statistics']['damage_by_type']['fire'] >= 15, 'getStatistics: fire damage >= 15');
assert_true(isset($res['damage_statistics']['damage_by_type']['slashing']), 'getStatistics: has slashing damage');
assert_true($res['damage_statistics']['damage_by_type']['slashing'] >= 8, 'getStatistics: slashing damage >= 8');

assert_true($res['avg_actions_per_round'] > 0, 'getStatistics: avg_actions_per_round > 0');

// ============================================================================
echo "\n--- Test 33: getStatistics — nonexistent encounter ---\n";
// ============================================================================
$response = $controller->getStatistics(99999);
assert_equals(404, $response->getStatusCode(), 'getStatistics missing: status 404');

// ============================================================================
echo "\n--- Test 34: updateHP — damage to 0 HP (defeated) ---\n";
// ============================================================================
$doomed_id = create_participant($db, $test_encounter_id, 'Doomed', 5, 5);
$req = make_request(['change_type' => 'damage', 'amount' => 10, 'damage_type' => 'bludgeoning']);
$res = decode_response($controller->updateHP($test_encounter_id, $doomed_id, $req));

assert_true($res['hp_after'] <= 0, 'updateHP lethal: hp_after <= 0');

// ============================================================================
echo "\n--- Test 35: updateHP — healing does not exceed max ---\n";
// ============================================================================
$topped_id = create_participant($db, $test_encounter_id, 'Healthy', 48, 50);
$req = make_request(['change_type' => 'healing', 'amount' => 20, 'source' => 'heal']);
$res = decode_response($controller->updateHP($test_encounter_id, $topped_id, $req));

// Healing should cap at max_hp.
assert_true($res['hp_after'] <= 50, 'updateHP overheal: hp_after <= 50 (max)');

// ============================================================================
echo "\n--- Test 36: addParticipant logs to combat_actions ---\n";
// ============================================================================
$req = make_request(['name' => 'LogTestNPC', 'team' => 'ally', 'hp' => 10, 'max_hp' => 10]);
$response = $controller->addParticipant($test_encounter_id, $req);
$res = decode_response($response);
$npc_id = $res['participant_id'];

// Check the log for a join action for this participant.
$log_req = make_get_request(['participant_id' => $npc_id, 'action_type' => 'join']);
$log_res = decode_response($controller->getLog($test_encounter_id, $log_req));
assert_true($log_res['meta']['total'] >= 1, 'addParticipant log: join action logged');

// ============================================================================
echo "\n--- Test 37: removeParticipant logs to combat_actions ---\n";
// ============================================================================
$req = make_request(['reason' => 'surrendered']);
$controller->removeParticipant($test_encounter_id, $npc_id, $req);

$log_req = make_get_request(['participant_id' => $npc_id, 'action_type' => 'removed']);
$log_res = decode_response($controller->getLog($test_encounter_id, $log_req));
assert_true($log_res['meta']['total'] >= 1, 'removeParticipant log: removed action logged');

// ============================================================================
echo "\n--- Test 38: getInitiative includes defeated status ---\n";
// ============================================================================
$res = decode_response($controller->getInitiative($test_encounter_id));
$defeated_count = 0;
foreach ($res['initiative_order'] as $p) {
  if ($p['is_defeated']) {
    $defeated_count++;
  }
}
assert_true($defeated_count >= 1, 'getInitiative: includes defeated participants');

// ============================================================================
echo "\n--- Test 39: updateParticipant — multiple fields ---\n";
// ============================================================================
$req = make_request([
  'name' => 'Fighter Elite',
  'hp' => 55,
  'max_hp' => 55,
  'ac' => 24,
  'actions_remaining' => 2,
  'reaction_available' => 0,
]);
$res = decode_response($controller->updateParticipant($test_encounter_id, $fighter_id, $req));

assert_equals(6, count($res['updated_fields']), 'updateParticipant multi: 6 fields updated');

$row = load_participant($db, $fighter_id);
assert_equals('Fighter Elite', $row['name'], 'updateParticipant multi DB: name updated');
assert_equals('55', $row['hp'], 'updateParticipant multi DB: hp = 55');
assert_equals('24', $row['ac'], 'updateParticipant multi DB: ac = 24');
assert_equals('2', $row['actions_remaining'], 'updateParticipant multi DB: actions_remaining = 2');
assert_equals('0', $row['reaction_available'], 'updateParticipant multi DB: reaction_available = 0');

// ============================================================================
echo "\n--- Test 40: applyCondition — with duration_remaining ---\n";
// ============================================================================
$req = make_request([
  'condition_type' => 'slowed',
  'value' => 1,
  'source' => 'web_spell',
  'duration_type' => 'rounds',
  'duration_remaining' => 3,
]);
$response = $controller->applyCondition($test_encounter_id, $wizard_id, $req);
$res = decode_response($response);

assert_equals(201, $response->getStatusCode(), 'applyCondition duration: status 201');
assert_equals('slowed', $res['condition_type'], 'applyCondition duration: condition_type = slowed');

// Verify via listConditions.
$list_res = decode_response($controller->listConditions($test_encounter_id, $wizard_id));
$found = FALSE;
foreach ($list_res['conditions'] as $c) {
  if ($c['condition_type'] === 'slowed') {
    $found = TRUE;
    assert_equals(1, $c['value'], 'applyCondition duration: value persisted');
  }
}
assert_true($found, 'applyCondition duration: slowed found in list');

// ============================================================================
// Cleanup
// ============================================================================
echo "\n--- Cleanup ---\n";
cleanup($db, $test_encounter_id);

// ============================================================================
// Summary
// ============================================================================
echo "\n=== CombatApiController Test Results ===\n";
echo "Passed: {$GLOBALS['test_pass']}\n";
echo "Failed: {$GLOBALS['test_fail']}\n";
echo "Total:  " . ($GLOBALS['test_pass'] + $GLOBALS['test_fail']) . "\n";

if (!empty($GLOBALS['test_errors'])) {
  echo "\nFailed tests:\n";
  foreach ($GLOBALS['test_errors'] as $err) {
    echo "  ✗ {$err}\n";
  }
}

echo "\n" . ($GLOBALS['test_fail'] === 0 ? '✓ ALL TESTS PASSED' : '✗ SOME TESTS FAILED') . "\n";
