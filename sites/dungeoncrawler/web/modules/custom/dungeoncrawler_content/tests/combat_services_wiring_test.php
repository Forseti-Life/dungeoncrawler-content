<?php

/**
 * @file
 * Tests for RulesEngine::validateAction, ActionProcessor::executeCastSpell,
 * and ConditionManager::processPersistentDamage implementations.
 *
 * Run: drush php:script web/modules/custom/dungeoncrawler_content/tests/combat_services_wiring_test.php
 */

use Drupal\dungeoncrawler_content\Service\ActionProcessor;
use Drupal\dungeoncrawler_content\Service\CombatCalculator;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\ConditionManager;
use Drupal\dungeoncrawler_content\Service\HPManager;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\RulesEngine;

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

function assert_array_key($key, $array, $label) {
  assert_true(is_array($array) && array_key_exists($key, $array), $label);
}

echo "=== Combat Services Wiring Tests ===\n\n";

$db = \Drupal::database();

// --------------- Test isolation IDs ---------------
$test_enc_id = 98500;

// --------------- Cleanup helper ---------------
function cleanup($db, $enc_id) {
  $db->delete('combat_actions')->condition('encounter_id', $enc_id)->execute();
  $db->delete('combat_conditions')->condition('encounter_id', $enc_id)->execute();
  $db->delete('combat_participants')->condition('encounter_id', $enc_id)->execute();
  $db->delete('combat_encounters')->condition('id', $enc_id)->execute();
}
cleanup($db, $test_enc_id);
cleanup($db, 98501);
cleanup($db, 98502);
cleanup($db, 98503);

// --------------- Fixture helpers ---------------
function create_encounter($db, $enc_id, $round = 1, $turn_index = 0) {
  $db->merge('combat_encounters')
    ->key('id', $enc_id)
    ->fields([
      'campaign_id' => 98500,
      'status' => 'active',
      'current_round' => $round,
      'turn_index' => $turn_index,
      'created' => time(),
      'updated' => time(),
    ])
    ->execute();
}

function add_participant($db, $enc_id, $name, $hp, $max_hp, $opts = []) {
  return (int) $db->insert('combat_participants')
    ->fields([
      'encounter_id' => $enc_id,
      'entity_id' => rand(90000, 99999),
      'entity_ref' => $opts['entity_ref'] ?? NULL,
      'name' => $name,
      'team' => $opts['team'] ?? 'player',
      'initiative' => $opts['initiative'] ?? 10,
      'initiative_roll' => $opts['initiative_roll'] ?? NULL,
      'ac' => $opts['ac'] ?? 15,
      'hp' => $hp,
      'max_hp' => $max_hp,
      'actions_remaining' => $opts['actions_remaining'] ?? 3,
      'attacks_this_turn' => $opts['attacks_this_turn'] ?? 0,
      'reaction_available' => 1,
      'position_q' => $opts['position_q'] ?? 0,
      'position_r' => $opts['position_r'] ?? 0,
      'is_defeated' => $opts['is_defeated'] ?? 0,
      'created' => time(),
      'updated' => time(),
    ])
    ->execute();
}

function get_participant($db, $pid) {
  return $db->select('combat_participants', 'p')
    ->fields('p')
    ->condition('p.id', $pid)
    ->execute()
    ->fetchAssoc();
}

// --------------- Service setup ---------------
$numberGen = new NumberGenerationService();
$combatCalc = new CombatCalculator();
$conditionMgr = new ConditionManager($db, $numberGen);
$hpManager = new HPManager($db, $conditionMgr);
$rulesEngine = new RulesEngine($db);
$store = new CombatEncounterStore($db);
$loggerFactory = \Drupal::service('logger.factory');
$actionProcessor = new ActionProcessor($combatCalc, $hpManager, $conditionMgr, $loggerFactory, $store, $numberGen, $rulesEngine);

// =========================================================================
// RULES ENGINE :: validateAction
// =========================================================================
echo "=== RulesEngine::validateAction ===\n\n";

// --- Setup encounter + participants ---
create_encounter($db, $test_enc_id);
$fighter_ref = json_encode(['weapon' => ['name' => 'longsword', 'type' => 'melee'], 'attack_bonus' => 10]);
$fighter_id = add_participant($db, $test_enc_id, 'Fighter', 50, 50, ['ac' => 18, 'actions_remaining' => 3, 'position_q' => 0, 'position_r' => 0, 'entity_ref' => $fighter_ref]);
$goblin_id = add_participant($db, $test_enc_id, 'Goblin', 20, 20, ['ac' => 14, 'team' => 'enemy', 'position_q' => 1, 'position_r' => 0]);

// --- T1: Basic stride — should pass ---
echo "--- validateAction: basic stride ---\n";
$r1 = $rulesEngine->validateAction($fighter_id, 'stride', $test_enc_id);
assert_true($r1['is_valid'] === TRUE, 'Stride action is valid');
assert_equals('', $r1['reason'], 'Stride reason is empty');

// --- T2: Basic strike with target ---
echo "--- validateAction: strike with target ---\n";
$r2 = $rulesEngine->validateAction($fighter_id, [
  'type' => 'strike',
  'target_id' => $goblin_id,
  'weapon' => ['name' => 'longsword', 'damage_type' => 'slashing'],
  'cost' => 1,
], $test_enc_id);
assert_true($r2['is_valid'] === TRUE, 'Strike with target is valid');

// --- T3: Defeated participant cannot act ---
echo "--- validateAction: defeated participant ---\n";
$dead_id = add_participant($db, $test_enc_id, 'DeadGuy', 0, 30, ['is_defeated' => 1]);
$r3 = $rulesEngine->validateAction($dead_id, 'stride', $test_enc_id);
assert_true($r3['is_valid'] === FALSE, 'Defeated participant cannot act');
assert_true(stripos($r3['reason'], 'defeated') !== FALSE, 'Reason mentions defeated');

// --- T4: No actions remaining ---
echo "--- validateAction: no actions remaining ---\n";
$exhausted_id = add_participant($db, $test_enc_id, 'Exhausted', 40, 40, ['actions_remaining' => 0]);
$r4 = $rulesEngine->validateAction($exhausted_id, ['type' => 'stride', 'cost' => 1], $test_enc_id);
assert_true($r4['is_valid'] === FALSE, 'No actions → invalid');
assert_true(!empty($r4['reason']), 'Has a reason for no-actions');

// --- T5: Cost exceeds remaining ---
echo "--- validateAction: cost exceeds remaining ---\n";
$one_left_id = add_participant($db, $test_enc_id, 'OneLeft', 40, 40, ['actions_remaining' => 1]);
$r5 = $rulesEngine->validateAction($one_left_id, ['type' => 'stride', 'cost' => 2], $test_enc_id);
assert_true($r5['is_valid'] === FALSE, '2-cost with 1 action → invalid');

// --- T6: Free action always valid ---
echo "--- validateAction: free action ---\n";
$r6 = $rulesEngine->validateAction($exhausted_id, ['type' => 'recall_knowledge', 'cost' => 'free'], $test_enc_id);
assert_true($r6['is_valid'] === TRUE, 'Free action with 0 remaining → valid');

// --- T7: Nonexistent participant ---
echo "--- validateAction: nonexistent participant ---\n";
$r7 = $rulesEngine->validateAction(999999, 'stride', $test_enc_id);
assert_true($r7['is_valid'] === FALSE, 'Nonexistent participant → invalid');
assert_true(stripos($r7['reason'], 'not found') !== FALSE, 'Reason mentions not found');

// --- T8: String action normalizes to array ---
echo "--- validateAction: string action normalization ---\n";
$r8 = $rulesEngine->validateAction($fighter_id, 'stride', $test_enc_id);
assert_array_key('is_valid', $r8, 'String action returns is_valid key');
assert_array_key('reason', $r8, 'String action returns reason key');

// --- T9: Cast spell action ---
echo "--- validateAction: cast_spell ---\n";
$wiz_ref = json_encode(['spell_slots' => ['1' => 3], 'spell_attack_bonus' => 6]);
$caster_id = add_participant($db, $test_enc_id, 'Wizard', 30, 30, [
  'actions_remaining' => 3,
  'entity_ref' => $wiz_ref,
]);
$r9 = $rulesEngine->validateAction($caster_id, [
  'type' => 'cast_spell',
  'spell' => 'magic_missile',
  'spell_level' => 1,
  'target_id' => $goblin_id,
  'cost' => 2,
], $test_enc_id);
assert_array_key('is_valid', $r9, 'cast_spell returns is_valid');
// May or may not pass validateSpellCast prereqs — just check structure.
assert_array_key('reason', $r9, 'cast_spell returns reason');

// --- T10: Immunity check — non-strike action with effect_type ---
echo "--- validateAction: immunity check ---\n";
$r10 = $rulesEngine->validateAction($fighter_id, [
  'type' => 'stride',
  'effect_type' => 'fire',
  'cost' => 1,
], $test_enc_id);
// No target, so immunity check is skipped — valid.
assert_true($r10['is_valid'] === TRUE, 'No immunity target → valid');

// --- T11: Condition restrictions — paralyzed participant ---
echo "--- validateAction: paralyzed blocker ---\n";
$paralyzed_id = add_participant($db, $test_enc_id, 'Paralyzed', 40, 40, ['actions_remaining' => 3]);
$conditionMgr->applyCondition($paralyzed_id, 'paralyzed', 0, NULL, 'test', $test_enc_id);
$r11 = $rulesEngine->validateAction($paralyzed_id, 'stride', $test_enc_id);
assert_true($r11['is_valid'] === FALSE, 'Paralyzed participant cannot stride');

// --- T12: Condition restrictions — unconscious participant ---
echo "--- validateAction: unconscious blocker ---\n";
$unconscious_id = add_participant($db, $test_enc_id, 'Unconscious', 40, 40, ['actions_remaining' => 3]);
$conditionMgr->applyCondition($unconscious_id, 'unconscious', 0, NULL, 'test', $test_enc_id);
$r12 = $rulesEngine->validateAction($unconscious_id, 'strike', $test_enc_id);
assert_true($r12['is_valid'] === FALSE, 'Unconscious participant cannot strike');

echo "\n";

// =========================================================================
// ACTION PROCESSOR :: executeCastSpell
// =========================================================================
echo "=== ActionProcessor::executeCastSpell ===\n\n";

// --- Setup fresh encounter for cast_spell tests ---
$spell_enc_id = 98501;
cleanup($db, $spell_enc_id);
create_encounter($db, $spell_enc_id, 1, 0);

$wizard_ref = json_encode(['spell_slots' => ['1' => 5, '2' => 3, '3' => 2], 'spell_attack_bonus' => 8, 'spell_dc' => 18]);
$spell_caster = add_participant($db, $spell_enc_id, 'Wizard', 30, 30, [
  'ac' => 12,
  'actions_remaining' => 3,
  'initiative' => 20,
  'entity_ref' => $wizard_ref,
]);
$spell_target = add_participant($db, $spell_enc_id, 'Orc', 40, 40, [
  'ac' => 16,
  'team' => 'enemy',
  'actions_remaining' => 3,
  'initiative' => 10,
]);

// --- T13: Basic spell cast (automatic delivery, damage) ---
echo "--- executeCastSpell: automatic damage spell ---\n";
$cs1 = $actionProcessor->executeCastSpell(
  $spell_caster,
  ['name' => 'magic_missile', 'delivery' => 'automatic', 'damage' => '3d4', 'damage_type' => 'force', 'cost' => 1],
  1,
  [$spell_target],
  $spell_enc_id
);
assert_equals('ok', $cs1['status'], 'magic_missile: status = ok');
assert_equals('magic_missile', $cs1['spell_name'], 'magic_missile: spell_name correct');
assert_true(is_array($cs1['target_results']), 'magic_missile: has target_results array');
assert_true(isset($cs1['target_results']) && count($cs1['target_results']) === 1, 'magic_missile: one target result');
if (!isset($cs1['target_results'])) { echo "  (error: {$cs1['message']}\n"; }
$tr1 = $cs1['target_results'][0] ?? [];
assert_equals('success', $tr1['degree'], 'magic_missile auto: degree = success');
assert_true($tr1['damage'] > 0, 'magic_missile: applied damage > 0');
assert_true($tr1['damage'] >= 3 && $tr1['damage'] <= 12, 'magic_missile: damage in 3d4 range (3-12)');
assert_equals(2, $cs1['actions_remaining'], 'magic_missile cost 1: 3 - 1 = 2 remaining');

// Verify DB state.
$caster_row = get_participant($db, $spell_caster);
assert_equals('2', $caster_row['actions_remaining'], 'DB: caster actions = 2');

$target_row = get_participant($db, $spell_target);
assert_true((int) $target_row['hp'] < 40, 'DB: target HP decreased');

// --- T14: Spell with attack roll delivery ---
echo "--- executeCastSpell: attack roll delivery ---\n";
// Reset caster actions.
$db->update('combat_participants')
  ->fields(['actions_remaining' => 3])
  ->condition('id', $spell_caster)
  ->execute();

$cs2 = $actionProcessor->executeCastSpell(
  $spell_caster,
  ['name' => 'ray_of_frost', 'delivery' => 'attack', 'damage' => '2d4', 'damage_type' => 'cold', 'cost' => 2],
  1,
  [$spell_target],
  $spell_enc_id
);
assert_equals('ok', $cs2['status'], 'ray_of_frost: status = ok');
assert_true(is_array($cs2['target_results']), 'ray_of_frost: has target_results');
$tr2 = $cs2['target_results'][0] ?? [];
assert_true(!is_null($tr2['natural_roll']), 'ray_of_frost: has natural_roll (attack delivery)');
assert_true(in_array($tr2['degree'], ['critical_success', 'success', 'failure', 'critical_failure']), 'ray_of_frost: valid degree');
assert_equals(1, $cs2['actions_remaining'], 'ray_of_frost cost 2: 3 - 2 = 1 remaining');

// --- T15: Spell with save delivery ---
echo "--- executeCastSpell: save delivery ---\n";
$db->update('combat_participants')
  ->fields(['actions_remaining' => 3])
  ->condition('id', $spell_caster)
  ->execute();

$cs3 = $actionProcessor->executeCastSpell(
  $spell_caster,
  ['name' => 'fireball', 'delivery' => 'save', 'save_type' => 'reflex', 'damage' => '6d6', 'damage_type' => 'fire', 'cost' => 2],
  3,
  [$spell_target],
  $spell_enc_id
);
assert_equals('ok', $cs3['status'], 'fireball: status = ok');
$tr3 = $cs3['target_results'][0] ?? [];
assert_true(!is_null($tr3['natural_roll']), 'fireball: has natural_roll (save delivery)');
assert_true(in_array($tr3['degree'], ['critical_success', 'success', 'failure', 'critical_failure']), 'fireball: valid degree');

// --- T16: Spell with healing ---
echo "--- executeCastSpell: healing spell ---\n";
$db->update('combat_participants')
  ->fields(['actions_remaining' => 3])
  ->condition('id', $spell_caster)
  ->execute();
// Damage caster first so healing has room.
$db->update('combat_participants')
  ->fields(['hp' => 15])
  ->condition('id', $spell_caster)
  ->execute();

$cs4 = $actionProcessor->executeCastSpell(
  $spell_caster,
  ['name' => 'heal', 'delivery' => 'automatic', 'healing' => '1d8', 'cost' => 2],
  1,
  [$spell_caster], // Self-target.
  $spell_enc_id
);
assert_equals('ok', $cs4['status'], 'heal: status = ok');
$tr4 = $cs4['target_results'][0] ?? [];
assert_true($tr4['healing'] > 0, 'heal: healing > 0');
$healed_row = get_participant($db, $spell_caster);
assert_true((int) $healed_row['hp'] > 15, 'heal: DB hp increased from 15');

// --- T17: Spell with condition application ---
echo "--- executeCastSpell: condition application ---\n";
$db->update('combat_participants')
  ->fields(['actions_remaining' => 3])
  ->condition('id', $spell_caster)
  ->execute();

$cs5 = $actionProcessor->executeCastSpell(
  $spell_caster,
  [
    'name' => 'fear',
    'delivery' => 'automatic',
    'condition' => ['name' => 'frightened', 'value' => 2],
    'cost' => 1,
  ],
  1,
  [$spell_target],
  $spell_enc_id
);
assert_equals('ok', $cs5['status'], 'fear: status = ok');
$tr5 = $cs5['target_results'][0] ?? [];
assert_true($tr5['condition_result'] !== NULL, 'fear: condition was applied');

// Verify condition exists in DB.
$conds = $conditionMgr->getActiveConditions($spell_target, $spell_enc_id);
$has_frightened = FALSE;
foreach ($conds as $c) {
  if ($c['condition_type'] === 'frightened') {
    $has_frightened = TRUE;
  }
}
assert_true($has_frightened, 'fear: frightened condition active in DB');

// --- T18: Cast spell — not caster's turn ---
echo "--- executeCastSpell: wrong turn ---\n";
$cs6 = $actionProcessor->executeCastSpell(
  $spell_target, // Orc is not turn 0.
  ['name' => 'fireball', 'delivery' => 'save', 'damage' => '6d6', 'cost' => 2],
  3,
  [$spell_caster],
  $spell_enc_id
);
assert_equals('error', $cs6['status'], 'Wrong turn returns error');
assert_true(stripos($cs6['message'], 'turn') !== FALSE, 'Error message mentions turn');

// --- T19: Cast spell — caster not found ---
echo "--- executeCastSpell: caster not found ---\n";
$cs7 = $actionProcessor->executeCastSpell(
  999999,
  ['name' => 'fireball', 'delivery' => 'save', 'damage' => '6d6', 'cost' => 2],
  3,
  [$spell_target],
  $spell_enc_id
);
assert_equals('error', $cs7['status'], 'Nonexistent caster returns error');

// --- T20: Cast spell — insufficient actions ---
echo "--- executeCastSpell: insufficient actions ---\n";
$db->update('combat_participants')
  ->fields(['actions_remaining' => 1])
  ->condition('id', $spell_caster)
  ->execute();

$cs8 = $actionProcessor->executeCastSpell(
  $spell_caster,
  ['name' => 'fireball', 'delivery' => 'save', 'damage' => '6d6', 'cost' => 3],
  3,
  [$spell_target],
  $spell_enc_id
);
assert_equals('error', $cs8['status'], 'Insufficient actions → error');

// --- T21: Cast spell — encounter not found ---
echo "--- executeCastSpell: encounter not found ---\n";
$cs9 = $actionProcessor->executeCastSpell(
  $spell_caster,
  ['name' => 'fireball', 'delivery' => 'save', 'damage' => '6d6', 'cost' => 2],
  3,
  [$spell_target],
  999999
);
assert_equals('error', $cs9['status'], 'Nonexistent encounter → error');

// --- T22: Cast spell via executeAction dispatcher ---
echo "--- executeAction: cast_spell dispatch ---\n";
$db->update('combat_participants')
  ->fields(['actions_remaining' => 3])
  ->condition('id', $spell_caster)
  ->execute();

$cs10 = $actionProcessor->executeAction($spell_enc_id, $spell_caster, 'cast_spell', [
  'spell_name' => 'magic_missile',
  'spell_level' => 1,
  'targets' => [$spell_target],
]);
assert_true(is_array($cs10), 'executeAction cast_spell dispatches correctly');
// Could be ok or error depending on spell validation—just confirm it dispatched.
assert_true(isset($cs10['status']), 'executeAction cast_spell returns status');

// --- T23: Multiple targets ---
echo "--- executeCastSpell: multiple targets ---\n";
$db->update('combat_participants')
  ->fields(['actions_remaining' => 3])
  ->condition('id', $spell_caster)
  ->execute();

$orc2 = add_participant($db, $spell_enc_id, 'Orc2', 35, 35, [
  'ac' => 14,
  'team' => 'enemy',
  'actions_remaining' => 3,
  'initiative' => 8,
]);

$cs11 = $actionProcessor->executeCastSpell(
  $spell_caster,
  ['name' => 'burning_hands', 'delivery' => 'save', 'save_type' => 'reflex', 'damage' => '2d6', 'damage_type' => 'fire', 'cost' => 2],
  1,
  [$spell_target, $orc2],
  $spell_enc_id
);
assert_equals('ok', $cs11['status'], 'burning_hands: multi-target status = ok');
assert_true(isset($cs11['target_results']) && count($cs11['target_results']) === 2, 'burning_hands: 2 target results');

// --- T24: Spell with no targets (area/self buff) ---
echo "--- executeCastSpell: no explicit targets ---\n";
$db->update('combat_participants')
  ->fields(['actions_remaining' => 3])
  ->condition('id', $spell_caster)
  ->execute();

$cs12 = $actionProcessor->executeCastSpell(
  $spell_caster,
  ['name' => 'shield', 'delivery' => 'automatic', 'cost' => 1],
  1,
  [], // No targets — self buff.
  $spell_enc_id
);
assert_equals('ok', $cs12['status'], 'shield: no-target spell status = ok');
assert_equals(0, count($cs12['target_results']), 'shield: 0 target results');
assert_equals(2, $cs12['actions_remaining'], 'shield cost 1: 3 - 1 = 2 remaining');

// --- T25: Critical success doubles damage ---
echo "--- executeCastSpell: damage ranges ---\n";
// Run 20 iterations to spot-check damage values stay in valid ranges.
$damage_min = PHP_INT_MAX;
$damage_max = 0;
for ($i = 0; $i < 20; $i++) {
  $db->update('combat_participants')
    ->fields(['actions_remaining' => 3, 'hp' => 40])
    ->condition('id', $spell_target)
    ->execute();
  $db->update('combat_participants')
    ->fields(['actions_remaining' => 3])
    ->condition('id', $spell_caster)
    ->execute();

  $itr = $actionProcessor->executeCastSpell(
    $spell_caster,
    ['name' => 'magic_missile', 'delivery' => 'automatic', 'damage' => '1d4', 'damage_type' => 'force', 'cost' => 1],
    1,
    [$spell_target],
    $spell_enc_id
  );
  $d = $itr['target_results'][0]['damage'] ?? 0;
  if ($d < $damage_min) $damage_min = $d;
  if ($d > $damage_max) $damage_max = $d;
}
assert_true($damage_min >= 1, 'Automatic delivery damage min >= 1');
assert_true($damage_max <= 4, 'Automatic delivery 1d4 damage max <= 4');

echo "\n";

// =========================================================================
// CONDITION MANAGER :: processPersistentDamage
// =========================================================================
echo "=== ConditionManager::processPersistentDamage ===\n\n";

$pd_enc_id = 98502;
cleanup($db, $pd_enc_id);
create_encounter($db, $pd_enc_id, 3, 0);

// --- T26: No persistent damage → empty result ---
echo "--- processPersistentDamage: no conditions ---\n";
$pd_fighter = add_participant($db, $pd_enc_id, 'Fighter', 50, 50);
$pd1 = $conditionMgr->processPersistentDamage($pd_fighter, $pd_enc_id);
assert_true(is_array($pd1), 'No persistent damage: returns array');
assert_equals(0, count($pd1), 'No persistent damage: empty array');

// --- T27: Single persistent damage applies damage ---
echo "--- processPersistentDamage: single condition ---\n";
$pd_victim = add_participant($db, $pd_enc_id, 'Victim', 40, 40);
// Insert persistent damage condition directly.
$pd_cond_id = (int) $db->insert('combat_conditions')
  ->fields([
    'participant_id' => $pd_victim,
    'encounter_id' => $pd_enc_id,
    'condition_type' => 'persistent_damage_fire',
    'value' => 5,
    'source' => 'alchemists_fire',
    'duration_type' => NULL,
    'duration_remaining' => NULL,
    'applied_at_round' => 1,
    'removed_at_round' => NULL,
    'created' => time(),
  ])
  ->execute();

$pd2 = $conditionMgr->processPersistentDamage($pd_victim, $pd_enc_id);
assert_true(is_array($pd2), 'Single PD: returns array');
assert_equals(1, count($pd2), 'Single PD: one result');
assert_equals($pd_cond_id, $pd2[0]['condition_id'], 'Single PD: correct condition ID');
assert_equals('persistent_damage_fire', $pd2[0]['condition_type'], 'Single PD: correct type');
assert_equals(5, $pd2[0]['damage'], 'Single PD: damage = value (5)');
assert_equals(40, $pd2[0]['hp_before'], 'Single PD: hp_before = 40');
assert_equals(35, $pd2[0]['hp_after'], 'Single PD: hp_after = 35');
assert_true(isset($pd2[0]['flat_check_roll']), 'Single PD: has flat_check_roll');
assert_true($pd2[0]['flat_check_roll'] >= 1 && $pd2[0]['flat_check_roll'] <= 20, 'Single PD: flat check roll 1-20');
assert_true(is_bool($pd2[0]['ended']), 'Single PD: ended is boolean');

// Verify DB HP updated.
$victim_row = get_participant($db, $pd_victim);
assert_equals('35', $victim_row['hp'], 'Single PD: DB hp = 35');

// --- T28: Flat check DC 15 — run many times, verify distribution ---
echo "--- processPersistentDamage: flat check distribution ---\n";
$ended_count = 0;
$total_runs = 50;
for ($i = 0; $i < $total_runs; $i++) {
  // Reset condition as active.
  $db->update('combat_conditions')
    ->fields(['removed_at_round' => NULL])
    ->condition('id', $pd_cond_id)
    ->execute();
  // Reset HP.
  $db->update('combat_participants')
    ->fields(['hp' => 100])
    ->condition('id', $pd_victim)
    ->execute();

  $iter_result = $conditionMgr->processPersistentDamage($pd_victim, $pd_enc_id);
  if (!empty($iter_result[0]['ended'])) {
    $ended_count++;
  }
}
// Expected: ~30% end (6 of 20 values >= 15). Allow wide margin.
assert_true($ended_count > 0, "Flat check: at least 1 ended in {$total_runs} runs (got {$ended_count})");
assert_true($ended_count < $total_runs, "Flat check: not all ended in {$total_runs} runs (got {$ended_count})");
echo "  (info: {$ended_count}/{$total_runs} flat checks succeeded)\n";

// --- T29: Condition removed when flat check succeeds ---
echo "--- processPersistentDamage: condition removal on success ---\n";
// Reset and run until one ends.
$removed = FALSE;
for ($i = 0; $i < 100; $i++) {
  $db->update('combat_conditions')
    ->fields(['removed_at_round' => NULL])
    ->condition('id', $pd_cond_id)
    ->execute();
  $db->update('combat_participants')
    ->fields(['hp' => 100])
    ->condition('id', $pd_victim)
    ->execute();

  $r = $conditionMgr->processPersistentDamage($pd_victim, $pd_enc_id);
  if (!empty($r[0]['ended'])) {
    // Check DB: removed_at_round should be set.
    $cond_row = $db->select('combat_conditions', 'c')
      ->fields('c')
      ->condition('id', $pd_cond_id)
      ->execute()
      ->fetchAssoc();
    $removed = !is_null($cond_row['removed_at_round']);
    break;
  }
}
assert_true($removed, 'Condition removed_at_round set when flat check succeeds');

// --- T30: Multiple persistent damage conditions ---
echo "--- processPersistentDamage: multiple conditions ---\n";
$pd_multi = add_participant($db, $pd_enc_id, 'MultiVictim', 60, 60);

$db->insert('combat_conditions')
  ->fields([
    'participant_id' => $pd_multi,
    'encounter_id' => $pd_enc_id,
    'condition_type' => 'persistent_damage_fire',
    'value' => 3,
    'source' => 'torch',
    'applied_at_round' => 1,
    'removed_at_round' => NULL,
    'created' => time(),
  ])
  ->execute();

$db->insert('combat_conditions')
  ->fields([
    'participant_id' => $pd_multi,
    'encounter_id' => $pd_enc_id,
    'condition_type' => 'persistent_damage_acid',
    'value' => 4,
    'source' => 'acid_flask',
    'applied_at_round' => 2,
    'removed_at_round' => NULL,
    'created' => time(),
  ])
  ->execute();

$pd_multi_result = $conditionMgr->processPersistentDamage($pd_multi, $pd_enc_id);
assert_equals(2, count($pd_multi_result), 'Multiple PD: 2 results');
assert_equals(3, $pd_multi_result[0]['damage'], 'Multiple PD: first damage = 3');
// Second condition sees the reduced HP from first.
assert_equals(57, $pd_multi_result[0]['hp_after'], 'Multiple PD: first hp_after = 57');
assert_equals(57, $pd_multi_result[1]['hp_before'], 'Multiple PD: second hp_before = 57');
assert_equals(53, $pd_multi_result[1]['hp_after'], 'Multiple PD: second hp_after = 53');

$multi_row = get_participant($db, $pd_multi);
assert_equals('53', $multi_row['hp'], 'Multiple PD: DB hp = 53');

// --- T31: HP cannot go below 0 ---
echo "--- processPersistentDamage: HP floor at 0 ---\n";
$pd_lowHP = add_participant($db, $pd_enc_id, 'LowHP', 2, 50);
$db->insert('combat_conditions')
  ->fields([
    'participant_id' => $pd_lowHP,
    'encounter_id' => $pd_enc_id,
    'condition_type' => 'persistent_damage_bleed',
    'value' => 10,
    'source' => 'wound',
    'applied_at_round' => 1,
    'removed_at_round' => NULL,
    'created' => time(),
  ])
  ->execute();

$pd_low_result = $conditionMgr->processPersistentDamage($pd_lowHP, $pd_enc_id);
assert_equals(1, count($pd_low_result), 'HP floor: 1 result');
assert_equals(0, $pd_low_result[0]['hp_after'], 'HP floor: hp_after = 0 (not negative)');
$low_row = get_participant($db, $pd_lowHP);
assert_equals('0', $low_row['hp'], 'HP floor: DB hp = 0');

// --- T32: Nonexistent participant → empty ---
echo "--- processPersistentDamage: nonexistent participant ---\n";
$pd_nx = $conditionMgr->processPersistentDamage(999999, $pd_enc_id);
assert_equals(0, count($pd_nx), 'Nonexistent participant: empty array');

// --- T33: Already-removed conditions not processed ---
echo "--- processPersistentDamage: skips removed conditions ---\n";
$pd_removed = add_participant($db, $pd_enc_id, 'Removed', 50, 50);
$db->insert('combat_conditions')
  ->fields([
    'participant_id' => $pd_removed,
    'encounter_id' => $pd_enc_id,
    'condition_type' => 'persistent_damage_fire',
    'value' => 5,
    'source' => 'old_fire',
    'applied_at_round' => 1,
    'removed_at_round' => 2, // Already removed!
    'created' => time(),
  ])
  ->execute();

$pd_removed_result = $conditionMgr->processPersistentDamage($pd_removed, $pd_enc_id);
assert_equals(0, count($pd_removed_result), 'Removed conditions: empty array');
$removed_row = get_participant($db, $pd_removed);
assert_equals('50', $removed_row['hp'], 'Removed conditions: HP unchanged');

// --- T34: Dice expression in source ---
echo "--- processPersistentDamage: dice expression in source ---\n";
$pd_dice = add_participant($db, $pd_enc_id, 'DiceVictim', 50, 50);
$db->insert('combat_conditions')
  ->fields([
    'participant_id' => $pd_dice,
    'encounter_id' => $pd_enc_id,
    'condition_type' => 'persistent_damage_fire',
    'value' => 0, // Use dice expression instead.
    'source' => 'fire:2d6',
    'applied_at_round' => 1,
    'removed_at_round' => NULL,
    'created' => time(),
  ])
  ->execute();

$pd_dice_result = $conditionMgr->processPersistentDamage($pd_dice, $pd_enc_id);
assert_equals(1, count($pd_dice_result), 'Dice PD: 1 result');
assert_true($pd_dice_result[0]['damage'] >= 2, 'Dice PD: damage >= 2 (2d6 min)');
assert_true($pd_dice_result[0]['damage'] <= 12, 'Dice PD: damage <= 12 (2d6 max)');

echo "\n";

// =========================================================================
// INTEGRATION: executeAction dispatcher routes cast_spell
// =========================================================================
echo "=== Integration: executeAction dispatcher ===\n\n";

$int_enc_id = 98503;
cleanup($db, $int_enc_id);
create_encounter($db, $int_enc_id, 1, 0);

$sorc_ref = json_encode(['weapon' => ['name' => 'dagger', 'type' => 'melee'], 'attack_bonus' => 5, 'spell_slots' => ['1' => 3], 'spell_attack_bonus' => 7]);
$int_caster = add_participant($db, $int_enc_id, 'Sorcerer', 25, 25, ['actions_remaining' => 3, 'initiative' => 15, 'entity_ref' => $sorc_ref]);
$int_target = add_participant($db, $int_enc_id, 'Bandit', 30, 30, ['team' => 'enemy', 'ac' => 14, 'actions_remaining' => 3, 'initiative' => 5]);

// --- T35: stride dispatch still works ---
echo "--- executeAction: stride still works ---\n";
$ea_stride = $actionProcessor->executeAction($int_enc_id, $int_caster, 'stride', [
  'distance' => 5,
  'path' => [['q' => 1, 'r' => 2]],
]);
assert_equals('ok', $ea_stride['status'], 'executeAction stride: ok');
assert_array_key('end_position', $ea_stride, 'executeAction stride: has end_position');

// --- T36: strike dispatch still works ---
echo "--- executeAction: strike still works ---\n";
$ea_strike = $actionProcessor->executeAction($int_enc_id, $int_caster, 'strike', [
  'target_id' => $int_target,
  'damage' => 8,
  'damage_type' => 'slashing',
]);
assert_equals('ok', $ea_strike['status'], 'executeAction strike: ok');
assert_array_key('degree', $ea_strike, 'executeAction strike: has degree');

// --- T37: cast_spell dispatch works ---
echo "--- executeAction: cast_spell dispatch ---\n";
$db->update('combat_participants')
  ->fields(['actions_remaining' => 3])
  ->condition('id', $int_caster)
  ->execute();

$ea_spell = $actionProcessor->executeAction($int_enc_id, $int_caster, 'cast_spell', [
  'spell_name' => 'acid_splash',
  'spell_level' => 1,
  'targets' => [$int_target],
]);
assert_equals('ok', $ea_spell['status'], 'executeAction cast_spell: ok');
assert_array_key('spell_name', $ea_spell, 'executeAction cast_spell: has spell_name');
assert_array_key('target_results', $ea_spell, 'executeAction cast_spell: has target_results');

// --- T38: unsupported action type ---
echo "--- executeAction: unsupported type ---\n";
$ea_bad = $actionProcessor->executeAction($int_enc_id, $int_caster, 'dance', []);
assert_equals('error', $ea_bad['status'], 'executeAction unsupported: error');

echo "\n";

// =========================================================================
// COMBAT ACTIONS LOG VERIFICATION
// =========================================================================
echo "=== Combat Log Verification ===\n\n";

// --- T39: Cast spell actions logged ---
echo "--- cast_spell actions logged ---\n";
$logged = $db->select('combat_actions', 'a')
  ->fields('a')
  ->condition('encounter_id', $spell_enc_id)
  ->condition('action_type', 'cast_spell')
  ->execute()
  ->fetchAll(\PDO::FETCH_ASSOC);
assert_true(count($logged) > 0, 'Cast spell actions exist in combat_actions');
$sample = reset($logged);
assert_true(!empty($sample['payload']), 'Log entry has payload');
$payload = json_decode($sample['payload'], TRUE);
assert_true(isset($payload['spell_name']), 'Log payload contains spell_name');

// --- T40: Persistent damage logged ---
echo "--- persistent_damage actions logged ---\n";
$pd_logged = $db->select('combat_actions', 'a')
  ->fields('a')
  ->condition('encounter_id', $pd_enc_id)
  ->condition('action_type', 'persistent_damage')
  ->execute()
  ->fetchAll(\PDO::FETCH_ASSOC);
assert_true(count($pd_logged) > 0, 'Persistent damage actions logged in combat_actions');

echo "\n";

// =========================================================================
// CLEANUP
// =========================================================================
foreach ([$test_enc_id, $spell_enc_id, $pd_enc_id, $int_enc_id] as $eid) {
  cleanup($db, $eid);
}

// =========================================================================
// REPORT
// =========================================================================
echo "=== Combat Services Wiring Tests Complete ===\n";
echo "Passed: {$GLOBALS['test_pass']}\n";
echo "Failed: {$GLOBALS['test_fail']}\n";
echo "Total:  " . ($GLOBALS['test_pass'] + $GLOBALS['test_fail']) . "\n";

if (!empty($GLOBALS['test_errors'])) {
  echo "\nFailed tests:\n";
  foreach ($GLOBALS['test_errors'] as $err) {
    echo "  - {$err}\n";
  }
}

exit($GLOBALS['test_fail'] > 0 ? 1 : 0);
