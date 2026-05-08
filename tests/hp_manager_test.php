<?php
/**
 * @file
 * Functional tests for HPManager — hit points, temp HP, dying, and death.
 *
 * Run with: drush php:script web/modules/custom/dungeoncrawler_content/tests/hp_manager_test.php
 *
 * Tests PF2e HP rules:
 *   - Basic damage and healing
 *   - Temporary HP (take-higher-value stacking rule)
 *   - Temp HP absorbs damage first
 *   - Dying/wounded condition integration
 *   - Massive damage instant death
 *   - Stabilization
 */

use Drupal\dungeoncrawler_content\Service\HPManager;
use Drupal\dungeoncrawler_content\Service\ConditionManager;

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

echo "=== HPManager Tests ===\n\n";

/** @var HPManager $hpManager */
$hpManager = \Drupal::service('dungeoncrawler_content.hp_manager');
/** @var ConditionManager $conditionManager */
$conditionManager = \Drupal::service('dungeoncrawler_content.condition_manager');
$db = \Drupal::database();

// Test encounter — use a high ID range unlikely to conflict.
$test_encounter_id = 99000;
$now = time();

// Helper: insert a test encounter row.
function create_test_encounter($db, $encounter_id) {
  $db->merge('combat_encounters')
    ->key('id', $encounter_id)
    ->fields([
      'campaign_id' => 99000,
      'status' => 'active',
      'current_round' => 1,
      'turn_index' => 0,
      'created' => time(),
      'updated' => time(),
    ])
    ->execute();
}

// Helper: insert a test participant and return the inserted ID.
function create_participant($db, $encounter_id, $name, $hp, $max_hp, $temp_hp = 0) {
  return (int) $db->insert('combat_participants')
    ->fields([
      'encounter_id' => $encounter_id,
      'entity_id' => rand(90000, 99999),
      'name' => $name,
      'team' => 'player',
      'initiative' => 10,
      'ac' => 15,
      'hp' => $hp,
      'max_hp' => $max_hp,
      'temp_hp' => $temp_hp,
      'is_defeated' => 0,
      'created' => time(),
      'updated' => time(),
    ])
    ->execute();
}

// Helper: read participant row.
function load_participant($db, $id) {
  return $db->select('combat_participants', 'p')
    ->fields('p')
    ->condition('id', $id)
    ->execute()
    ->fetchAssoc();
}

// Helper: cleanup.
function cleanup($db, $encounter_id) {
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

// Clean slate.
cleanup($db, $test_encounter_id);
create_test_encounter($db, $test_encounter_id);

// ============================================================================
echo "--- Test 1: Basic damage ---\n";
// ============================================================================
$pid1 = create_participant($db, $test_encounter_id, 'Fighter', 30, 30);
$result = $hpManager->applyDamage($pid1, 10, 'slashing', 'goblin_sword', $test_encounter_id);

assert_equals(10, $result['final_damage'], 'applyDamage: final_damage = 10');
assert_equals(20, $result['new_hp'], 'applyDamage: new_hp = 20');
assert_equals(0, $result['temp_hp_used'], 'applyDamage: no temp HP used');
assert_equals(10, $result['hp_damage'], 'applyDamage: hp_damage = 10 (all to real HP)');
assert_equals('active', $result['new_status'], 'applyDamage: status remains active');

$row = load_participant($db, $pid1);
assert_equals('20', $row['hp'], 'DB: HP persisted to 20');

// ============================================================================
echo "\n--- Test 2: Healing (capped at max) ---\n";
// ============================================================================
$result = $hpManager->applyHealing($pid1, 50, 'cure_wounds', $test_encounter_id);
assert_equals(10, $result['healing_applied'], 'applyHealing: capped to 10 (30-20)');
assert_equals(30, $result['new_hp'], 'applyHealing: new_hp = max_hp (30)');

$row = load_participant($db, $pid1);
assert_equals('30', $row['hp'], 'DB: HP healed back to 30');

// ============================================================================
echo "\n--- Test 3: Healing partial (below cap) ---\n";
// ============================================================================
$pid2 = create_participant($db, $test_encounter_id, 'Wizard', 5, 20);
$result = $hpManager->applyHealing($pid2, 8, 'first_aid', $test_encounter_id);
assert_equals(8, $result['healing_applied'], 'applyHealing partial: 8 applied');
assert_equals(13, $result['new_hp'], 'applyHealing partial: new_hp = 13');

// ============================================================================
echo "\n--- Test 4: Apply temporary HP (basic) ---\n";
// ============================================================================
$pid3 = create_participant($db, $test_encounter_id, 'Sorcerer', 18, 18, 0);
$result = $hpManager->applyTemporaryHP($pid3, 10, 'False Life', $test_encounter_id);

assert_equals(0, $result['temp_hp_before'], 'applyTempHP: before = 0');
assert_equals(10, $result['temp_hp_after'], 'applyTempHP: after = 10');
assert_equals(TRUE, $result['applied'], 'applyTempHP: applied = TRUE');

$row = load_participant($db, $pid3);
assert_equals('10', $row['temp_hp'], 'DB: temp_hp persisted to 10');

// ============================================================================
echo "\n--- Test 5: Temp HP doesn't stack (take higher) ---\n";
// ============================================================================
// Already has 10 temp HP. Try applying 8 — should keep 10.
$result = $hpManager->applyTemporaryHP($pid3, 8, 'Aid spell', $test_encounter_id);
assert_equals(10, $result['temp_hp_before'], 'Temp no-stack: before = 10');
assert_equals(10, $result['temp_hp_after'], 'Temp no-stack: after still 10 (8 < 10)');
assert_equals(FALSE, $result['applied'], 'Temp no-stack: applied = FALSE');

$row = load_participant($db, $pid3);
assert_equals('10', $row['temp_hp'], 'DB: temp_hp unchanged at 10');

// ============================================================================
echo "\n--- Test 6: Temp HP upgrades when new value is higher ---\n";
// ============================================================================
$result = $hpManager->applyTemporaryHP($pid3, 15, 'Greater False Life', $test_encounter_id);
assert_equals(10, $result['temp_hp_before'], 'Temp upgrade: before = 10');
assert_equals(15, $result['temp_hp_after'], 'Temp upgrade: after = 15');
assert_equals(TRUE, $result['applied'], 'Temp upgrade: applied = TRUE');

$row = load_participant($db, $pid3);
assert_equals('15', $row['temp_hp'], 'DB: temp_hp upgraded to 15');

// ============================================================================
echo "\n--- Test 7: Same value is not applied (not strictly higher) ---\n";
// ============================================================================
$result = $hpManager->applyTemporaryHP($pid3, 15, 'Duplicate cast', $test_encounter_id);
assert_equals(FALSE, $result['applied'], 'Temp same-value: not applied (15 <= 15)');

// ============================================================================
echo "\n--- Test 8: Temp HP absorbs damage first ---\n";
// ============================================================================
$pid4 = create_participant($db, $test_encounter_id, 'Cleric', 25, 25, 10);
$result = $hpManager->applyDamage($pid4, 7, 'fire', 'fireball', $test_encounter_id);

assert_equals(7, $result['final_damage'], 'Temp absorb: final_damage = 7');
assert_equals(7, $result['temp_hp_used'], 'Temp absorb: 7 absorbed by temp HP');
assert_equals(0, $result['hp_damage'], 'Temp absorb: 0 damage to real HP');
assert_equals(25, $result['new_hp'], 'Temp absorb: real HP unchanged (25)');
assert_equals(3, $result['new_temp_hp'], 'Temp absorb: temp HP reduced to 3');
assert_equals('active', $result['new_status'], 'Temp absorb: still active');

$row = load_participant($db, $pid4);
assert_equals('25', $row['hp'], 'DB: HP unchanged at 25');
assert_equals('3', $row['temp_hp'], 'DB: temp_hp reduced to 3');

// ============================================================================
echo "\n--- Test 9: Damage exceeds temp HP (overflow to real HP) ---\n";
// ============================================================================
// pid4 has 25 HP and 3 temp HP.
$result = $hpManager->applyDamage($pid4, 13, 'bludgeoning', 'giant_club', $test_encounter_id);

assert_equals(13, $result['final_damage'], 'Temp overflow: final_damage = 13');
assert_equals(3, $result['temp_hp_used'], 'Temp overflow: 3 absorbed by temp HP');
assert_equals(10, $result['hp_damage'], 'Temp overflow: 10 damage to real HP');
assert_equals(15, $result['new_hp'], 'Temp overflow: new HP = 15 (25 - 10)');
assert_equals(0, $result['new_temp_hp'], 'Temp overflow: temp HP exhausted (0)');

$row = load_participant($db, $pid4);
assert_equals('15', $row['hp'], 'DB: HP = 15');
assert_equals('0', $row['temp_hp'], 'DB: temp_hp = 0');

// ============================================================================
echo "\n--- Test 10: Damage with no temp HP ---\n";
// ============================================================================
$pid5 = create_participant($db, $test_encounter_id, 'Rogue', 20, 20, 0);
$result = $hpManager->applyDamage($pid5, 5, 'piercing', 'arrow', $test_encounter_id);

assert_equals(0, $result['temp_hp_used'], 'No temp: 0 absorbed');
assert_equals(5, $result['hp_damage'], 'No temp: 5 to real HP');
assert_equals(15, $result['new_hp'], 'No temp: new HP = 15');

// ============================================================================
echo "\n--- Test 11: Defeat (HP reaches 0) ---\n";
// ============================================================================
$pid6 = create_participant($db, $test_encounter_id, 'Bard', 5, 20);
$result = $hpManager->applyDamage($pid6, 5, 'slashing', 'enemy_strike', $test_encounter_id);

assert_equals(0, $result['new_hp'], 'Defeat: new HP = 0');
assert_equals('defeated', $result['new_status'], 'Defeat: status = defeated');

$row = load_participant($db, $pid6);
assert_equals('1', $row['is_defeated'], 'DB: is_defeated = 1');

// ============================================================================
echo "\n--- Test 12: Defeat below zero (negative HP) ---\n";
// ============================================================================
$pid7 = create_participant($db, $test_encounter_id, 'Alchemist', 3, 20);
$result = $hpManager->applyDamage($pid7, 10, 'fire', 'alchemist_fire', $test_encounter_id);

assert_equals(-7, $result['new_hp'], 'Below zero: new HP = -7');
assert_equals('defeated', $result['new_status'], 'Below zero: defeated (not dead, -7 > -20)');

// ============================================================================
echo "\n--- Test 13: Massive damage (instant death) ---\n";
// ============================================================================
$pid8 = create_participant($db, $test_encounter_id, 'Commoner', 5, 20);
$result = $hpManager->applyDamage($pid8, 30, 'force', 'disintegrate', $test_encounter_id);

assert_equals(-25, $result['new_hp'], 'Massive damage: new HP = -25');
assert_equals('dead', $result['new_status'], 'Massive damage: instant death (HP <= -max_hp)');
assert_equals('hp_threshold', $result['death_reason'], 'Massive damage: reason = hp_threshold');

// ============================================================================
echo "\n--- Test 14: Defeat + temp HP (temp absorbs first, then HP drops to 0) ---\n";
// ============================================================================
$pid9 = create_participant($db, $test_encounter_id, 'Monk', 3, 20, 5);
// 10 damage: 5 absorbed by temp → 5 to real HP → new HP = 3 - 5 = -2.
$result = $hpManager->applyDamage($pid9, 10, 'bludgeoning', 'fist', $test_encounter_id);

assert_equals(5, $result['temp_hp_used'], 'Temp+defeat: 5 temp absorbed');
assert_equals(5, $result['hp_damage'], 'Temp+defeat: 5 to real HP');
assert_equals(-2, $result['new_hp'], 'Temp+defeat: new HP = -2');
assert_equals('defeated', $result['new_status'], 'Temp+defeat: defeated');

// ============================================================================
echo "\n--- Test 15: Zero damage does nothing ---\n";
// ============================================================================
$pid10 = create_participant($db, $test_encounter_id, 'Druid', 15, 20, 5);
$result = $hpManager->applyDamage($pid10, 0, 'untyped', 'nothing', $test_encounter_id);

assert_equals(0, $result['final_damage'], 'Zero damage: final = 0');
assert_equals(15, $result['new_hp'], 'Zero damage: HP unchanged');
assert_equals(0, $result['temp_hp_used'], 'Zero damage: no temp used');
assert_equals(5, $result['new_temp_hp'], 'Zero damage: temp unchanged');

// ============================================================================
echo "\n--- Test 16: Negative damage clamped to zero ---\n";
// ============================================================================
$result = $hpManager->applyDamage($pid10, -5, 'untyped', 'negative', $test_encounter_id);
assert_equals(0, $result['final_damage'], 'Negative damage clamped: final = 0');
assert_equals(15, $result['new_hp'], 'Negative damage clamped: HP unchanged');

// ============================================================================
echo "\n--- Test 17: Healing does not restore temp HP ---\n";
// ============================================================================
// pid4 has 15 HP, 0 temp HP, max 25.
$hpManager->applyHealing($pid4, 10, 'heal', $test_encounter_id);
$row = load_participant($db, $pid4);
assert_equals('25', $row['hp'], 'Healing restores HP to 25');
assert_equals('0', $row['temp_hp'], 'Healing: temp HP stays at 0 (not restored)');

// ============================================================================
echo "\n--- Test 18: Apply temp HP to nonexistent participant ---\n";
// ============================================================================
$result = $hpManager->applyTemporaryHP(999999, 10, 'test', $test_encounter_id);
assert_equals(FALSE, $result['applied'], 'Nonexistent: applied = FALSE');
assert_equals(0, $result['temp_hp_before'], 'Nonexistent: temp_hp_before = 0');

// ============================================================================
echo "\n--- Test 19: Damage to nonexistent participant ---\n";
// ============================================================================
$result = $hpManager->applyDamage(999999, 10, 'fire', 'test', $test_encounter_id);
assert_equals('not_found', $result['new_status'], 'Nonexistent damage: status = not_found');

// ============================================================================
echo "\n--- Test 20: Apply temp HP of zero (edge case) ---\n";
// ============================================================================
$pid11 = create_participant($db, $test_encounter_id, 'Ranger', 20, 20, 0);
$result = $hpManager->applyTemporaryHP($pid11, 0, 'nothing', $test_encounter_id);
assert_equals(FALSE, $result['applied'], 'Zero temp HP: not applied (0 <= 0)');

// ============================================================================
echo "\n--- Test 21: Damage log records entries ---\n";
// ============================================================================
$log_count = (int) $db->select('combat_damage_log', 'l')
  ->condition('encounter_id', $test_encounter_id)
  ->countQuery()
  ->execute()
  ->fetchField();
assert_greater_than(0, $log_count, "Damage log has {$log_count} entries for test encounter");

// ============================================================================
echo "\n--- Test 22: Stabilize character ---\n";
// ============================================================================
// Create a defeated participant with dying condition.
$pid12 = create_participant($db, $test_encounter_id, 'Dying Paladin', 0, 30);
$db->update('combat_participants')
  ->fields(['is_defeated' => 1])
  ->condition('id', $pid12)
  ->execute();
$conditionManager->applyCondition($pid12, 'dying', 2, ['type' => 'encounter', 'remaining' => NULL], 'test', $test_encounter_id);

$result = $hpManager->stabilizeCharacter($pid12, $test_encounter_id);
assert_equals(TRUE, $result, 'Stabilize: returns TRUE');

$row = load_participant($db, $pid12);
assert_equals('1', $row['hp'], 'Stabilize: HP set to 1');
assert_equals('0', $row['is_defeated'], 'Stabilize: is_defeated cleared');

// Check wounded condition was applied (dying 2 → wounded 1).
$active = $conditionManager->getActiveConditions($pid12, $test_encounter_id);
$has_wounded = FALSE;
$has_dying = FALSE;
foreach ($active as $cond) {
  if ($cond['condition_type'] === 'wounded') {
    $has_wounded = TRUE;
  }
  if ($cond['condition_type'] === 'dying') {
    $has_dying = TRUE;
  }
}
assert_equals(TRUE, $has_wounded, 'Stabilize: wounded condition applied');
assert_equals(FALSE, $has_dying, 'Stabilize: dying condition removed');

// ============================================================================
echo "\n--- Test 23: checkDeathCondition (alive) ---\n";
// ============================================================================
$death_check = $hpManager->checkDeathCondition($pid1, $test_encounter_id);
assert_equals(FALSE, $death_check['is_dead'], 'Death check alive: is_dead = FALSE');
assert_equals('', $death_check['death_reason'], 'Death check alive: no death_reason');

// ============================================================================
echo "\n--- Test 24: checkDeathCondition (massive damage) ---\n";
// ============================================================================
// pid8 was at -25 HP (max 20), so -25 <= -20 → dead.
$death_check = $hpManager->checkDeathCondition($pid8, $test_encounter_id);
assert_equals(TRUE, $death_check['is_dead'], 'Death check massive: is_dead = TRUE');
assert_equals('hp_threshold', $death_check['death_reason'], 'Death check massive: hp_threshold');

// ============================================================================
echo "\n--- Test 25: Full scenario — temp HP absorb + partial defeat ---\n";
// ============================================================================
// Ranger with 20 HP, 20 max, gains 12 temp HP, takes 28 damage.
// Expected: 12 temp absorbed, 16 to real HP → -16 + 20 = 4... wait.
// 20 HP - (28 - 12) = 20 - 16 = 4. Still alive.
$pid13 = create_participant($db, $test_encounter_id, 'Scenario Ranger', 20, 20, 0);
$hpManager->applyTemporaryHP($pid13, 12, 'Inspire Heroics', $test_encounter_id);
$result = $hpManager->applyDamage($pid13, 28, 'slashing', 'dragon_claw', $test_encounter_id);

assert_equals(28, $result['final_damage'], 'Scenario: total damage = 28');
assert_equals(12, $result['temp_hp_used'], 'Scenario: 12 temp absorbed');
assert_equals(16, $result['hp_damage'], 'Scenario: 16 to real HP');
assert_equals(4, $result['new_hp'], 'Scenario: new HP = 4 (20 - 16)');
assert_equals(0, $result['new_temp_hp'], 'Scenario: temp HP fully consumed');
assert_equals('active', $result['new_status'], 'Scenario: still active (HP > 0)');

// ============================================================================
echo "\n--- Test 26: Full scenario — temp HP absorb → defeat ---\n";
// ============================================================================
// Same ranger, now at 4 HP, 0 temp. Give 5 temp, take 12 damage.
// 5 absorbed, 7 to HP → 4 - 7 = -3. Defeated.
$hpManager->applyTemporaryHP($pid13, 5, 'Shield spell', $test_encounter_id);
$result = $hpManager->applyDamage($pid13, 12, 'acid', 'acid_splash', $test_encounter_id);

assert_equals(5, $result['temp_hp_used'], 'Scenario2: 5 temp absorbed');
assert_equals(7, $result['hp_damage'], 'Scenario2: 7 to real HP');
assert_equals(-3, $result['new_hp'], 'Scenario2: new HP = -3');
assert_equals('defeated', $result['new_status'], 'Scenario2: defeated');

// ============================================================================
echo "\n--- Cleanup ---\n";
// ============================================================================
cleanup($db, $test_encounter_id);
echo "  Cleaned up test data for encounter {$test_encounter_id}\n";

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
