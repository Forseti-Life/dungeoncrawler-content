<?php

/**
 * @file
 * Functional tests for combat engine services.
 *
 * Tests Calculator, StateManager, ReactionHandler, and RulesEngine
 * implementations against PF2e combat rules.
 *
 * Run: drush php:script web/modules/custom/dungeoncrawler_content/tests/combat_engine_test.php
 */

use Drupal\dungeoncrawler_content\Service\Calculator;
use Drupal\dungeoncrawler_content\Service\CombatCalculator;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\RulesEngine;

$GLOBALS['tests_passed'] = 0;
$GLOBALS['tests_failed'] = 0;

function assert_test(bool $condition, string $message): void {
  if ($condition) {
    $GLOBALS['tests_passed']++;
    echo "  ✓ {$message}\n";
  }
  else {
    $GLOBALS['tests_failed']++;
    echo "  ✗ FAIL: {$message}\n";
  }
}

// =========================================================================
// CALCULATOR TESTS
// =========================================================================

echo "=== Calculator Service ===\n\n";

$numberGen = new NumberGenerationService();
$combatCalc = new CombatCalculator();
$calc = new Calculator($numberGen, $combatCalc);

// --- Initiative ---
echo "--- Initiative Calculation ---\n";

$init = $calc->calculateInitiative(5, [2]);
assert_test(is_array($init), 'Initiative returns array');
assert_test($init['modifier'] === 5, 'Initiative modifier = 5');
assert_test($init['bonuses'] === 2, 'Initiative bonus sum = 2');
assert_test($init['roll'] >= 1 && $init['roll'] <= 20, 'Initiative roll is d20 (1-20)');
assert_test($init['total'] === $init['roll'] + 7, 'Initiative total = roll + modifier + bonuses');

$init_neg = $calc->calculateInitiative(-2);
assert_test($init_neg['modifier'] === -2, 'Negative perception modifier works');
assert_test($init_neg['bonuses'] === 0, 'No bonuses = 0');

// --- Initiative Order ---
echo "--- Initiative Order ---\n";

$participants = [
  ['id' => 1, 'initiative_total' => 15, 'tiebreaker' => 3, 'name' => 'Goblin'],
  ['id' => 2, 'initiative_total' => 20, 'tiebreaker' => 5, 'name' => 'Fighter'],
  ['id' => 3, 'initiative_total' => 15, 'tiebreaker' => 5, 'name' => 'Wizard'],
  ['id' => 4, 'initiative_total' => 15, 'tiebreaker' => 3, 'name' => 'Rogue'],
];

$sorted = $calc->sortInitiativeOrder($participants);
assert_test($sorted[0]['name'] === 'Fighter', 'Highest initiative goes first');
assert_test($sorted[1]['name'] === 'Wizard', 'Tied: higher tiebreaker goes first');
assert_test($sorted[2]['name'] === 'Goblin', 'Tied tiebreaker: lower ID goes first');
assert_test($sorted[3]['name'] === 'Rogue', 'Last participant correct');

// --- Attack Bonus ---
echo "--- Attack Bonus ---\n";

$bonus = $calc->calculateAttackBonus(8, 4, 1, 0, [], []);
assert_test($bonus === 13, 'Attack bonus = prof(8) + ability(4) + item(1) = 13');

$bonus_map = $calc->calculateAttackBonus(8, 4, 1, 5, [], []);
assert_test($bonus_map === 8, 'Attack bonus with MAP -5 = 8');

$bonus_full = $calc->calculateAttackBonus(8, 4, 1, 0, [1, 2], [1]);
assert_test($bonus_full === 15, 'Attack bonus with +3 bonuses -1 penalty = 15');

// --- Multiple Attack Penalty ---
echo "--- Multiple Attack Penalty ---\n";

$map1 = $calc->calculateMAP(1, false);
assert_test($map1 === 0, 'First attack: MAP = 0');

$map2 = $calc->calculateMAP(2, false);
assert_test($map2 === -5, 'Second attack (normal): MAP = -5');

$map2a = $calc->calculateMAP(2, true);
assert_test($map2a === -4, 'Second attack (agile): MAP = -4');

$map3 = $calc->calculateMAP(3, false);
assert_test($map3 === -10, 'Third attack (normal): MAP = -10');

$map3a = $calc->calculateMAP(3, true);
assert_test($map3a === -8, 'Third attack (agile): MAP = -8');

$map4 = $calc->calculateMAP(5, false);
assert_test($map4 === -10, 'Fifth attack: MAP stays at -10');

// --- Degree of Success ---
echo "--- Degree of Success ---\n";

$dos_cs = $calc->determineDegreeOfSuccess(30, 20, false, false);
assert_test($dos_cs === 'critical_success', 'Beat DC by 10+ = critical success');

$dos_s = $calc->determineDegreeOfSuccess(20, 20, false, false);
assert_test($dos_s === 'success', 'Meet DC exactly = success');

$dos_f = $calc->determineDegreeOfSuccess(15, 20, false, false);
assert_test($dos_f === 'failure', 'Below DC = failure');

$dos_cf = $calc->determineDegreeOfSuccess(10, 20, false, false);
assert_test($dos_cf === 'critical_failure', 'Miss DC by 10+ = critical failure');

// Natural 20 bumps up.
$dos_nat20 = $calc->determineDegreeOfSuccess(15, 20, false, true);
assert_test($dos_nat20 === 'success', 'Nat 20 bumps failure → success');

// Natural 1 bumps down.
$dos_nat1 = $calc->determineDegreeOfSuccess(20, 20, true, false);
assert_test($dos_nat1 === 'failure', 'Nat 1 bumps success → failure');

// Nat 20 on already-crit-success stays crit.
$dos_nat20_cs = $calc->determineDegreeOfSuccess(30, 20, false, true);
assert_test($dos_nat20_cs === 'critical_success', 'Nat 20 on crit success stays crit');

// --- Damage Rolling ---
echo "--- Damage Rolling ---\n";

$dmg = $calc->rollDamage('1d8', 4, [1]);
assert_test(is_array($dmg), 'rollDamage returns array');
assert_test(count($dmg['rolls']) === 1, 'rollDamage: 1d8 = 1 die');
assert_test($dmg['rolls'][0] >= 1 && $dmg['rolls'][0] <= 8, 'rollDamage: d8 roll in range');
assert_test($dmg['total'] === $dmg['dice_total'] + 4 + 1, 'rollDamage: total = dice + ability + bonuses');
assert_test($dmg['total'] >= 6 && $dmg['total'] <= 13, 'rollDamage: total in valid range (6-13)');

$dmg_multi = $calc->rollDamage('2d6+3', 0, []);
assert_test(count($dmg_multi['rolls']) === 2, 'rollDamage: 2d6 = 2 dice');
assert_test($dmg_multi['total'] >= 5 && $dmg_multi['total'] <= 15, 'rollDamage: 2d6+3 total in range');

// --- Critical Damage ---
echo "--- Critical Damage ---\n";

$crit = $calc->applyCriticalDamage([4, 6], 5);
assert_test($crit['base_dice'] === 10, 'Critical: base dice = 4+6 = 10');
assert_test($crit['base_static'] === 5, 'Critical: static modifiers = 5');
assert_test($crit['doubled_total'] === 30, 'Critical: (10+5)*2 = 30');

$crit_zero = $calc->applyCriticalDamage([], 0);
assert_test($crit_zero['doubled_total'] === 0, 'Critical: zero damage = zero');

// --- Resistances and Weaknesses ---
echo "--- Resistances & Weaknesses ---\n";

$rw = $calc->applyResistancesWeaknesses(10, 'fire', ['fire' => 5], []);
assert_test($rw['resistance_applied'] === 5, 'Fire resistance 5 applied');
assert_test($rw['final'] === 5, 'Fire 10 - resist 5 = 5');

$rw_weak = $calc->applyResistancesWeaknesses(10, 'fire', [], ['fire' => 3]);
assert_test($rw_weak['weakness_applied'] === 3, 'Fire weakness 3 applied');
assert_test($rw_weak['final'] === 13, 'Fire 10 + weakness 3 = 13');

$rw_both = $calc->applyResistancesWeaknesses(10, 'fire', ['fire' => 3], ['fire' => 5]);
assert_test($rw_both['final'] === 12, 'Fire 10 - resist 3 + weakness 5 = 12');

$rw_phys = $calc->applyResistancesWeaknesses(10, 'slashing', ['physical' => 5], []);
assert_test($rw_phys['final'] === 5, 'Physical resistance applies to slashing');

$rw_all = $calc->applyResistancesWeaknesses(10, 'acid', ['all' => 3], []);
assert_test($rw_all['final'] === 7, '"all" resistance applies to acid');

$rw_overkill = $calc->applyResistancesWeaknesses(5, 'fire', ['fire' => 10], []);
assert_test($rw_overkill['final'] === 0, 'Resistance > damage = 0 (not negative)');

// --- AC Calculation ---
echo "--- AC Calculation ---\n";

$ac = $calc->calculateAC(10, 3, 5, false, []);
assert_test($ac['total'] === 18, 'AC = 10 + dex(3) + armor(5) = 18');

$ac_shield = $calc->calculateAC(10, 3, 5, true, [], 2);
assert_test($ac_shield['total'] === 20, 'AC with shield = 18 + 2 = 20');
assert_test($ac_shield['shield'] === 2, 'Shield component = 2');

$ac_conditions = $calc->calculateAC(10, 3, 5, false, ['flat_footed' => -2, 'frightened' => -1]);
assert_test($ac_conditions['total'] === 15, 'AC with conditions = 18 - 3 = 15');
assert_test($ac_conditions['conditions'] === -3, 'Condition total = -3');

// --- Saving Throw ---
echo "--- Saving Throw ---\n";

$save = $calc->rollSavingThrow(3, 8, 1, [2]);
assert_test($save['roll'] >= 1 && $save['roll'] <= 20, 'Saving throw roll is d20');
assert_test($save['modifier'] === 14, 'Save modifier = 3+8+1+2 = 14');
assert_test($save['total'] === $save['roll'] + 14, 'Save total = roll + modifier');
assert_test(is_bool($save['is_natural_1']), 'is_natural_1 is bool');
assert_test(is_bool($save['is_natural_20']), 'is_natural_20 is bool');

// --- Skill Check ---
echo "--- Skill Check ---\n";

$skill = $calc->rollSkillCheck(4, 6, [1], [2]);
assert_test($skill['roll'] >= 1 && $skill['roll'] <= 20, 'Skill check roll is d20');
assert_test($skill['modifier'] === 9, 'Skill modifier = 4+6+1-2 = 9');

// =========================================================================
// RULES ENGINE TESTS
// =========================================================================

echo "\n=== Rules Engine ===\n\n";

$rulesEngine = \Drupal::service('dungeoncrawler_content.rules_engine');

// --- Action Economy ---
echo "--- Action Economy ---\n";

$ae_ok = $rulesEngine->validateActionEconomy(['actions_remaining' => 3], 1);
assert_test($ae_ok['is_valid'] === TRUE, 'Strike with 3 actions remaining = valid');
assert_test($ae_ok['actions_after'] === 2, 'Actions after = 2');

$ae_2cost = $rulesEngine->validateActionEconomy(['actions_remaining' => 3], 2);
assert_test($ae_2cost['is_valid'] === TRUE, '2-action activity with 3 available = valid');
assert_test($ae_2cost['actions_after'] === 1, 'Actions after 2-cost from 3 = 1');

$ae_fail = $rulesEngine->validateActionEconomy(['actions_remaining' => 1], 2);
assert_test($ae_fail['is_valid'] === FALSE, '2-action cost with 1 remaining = invalid');

$ae_free = $rulesEngine->validateActionEconomy(['actions_remaining' => 0], 'free');
assert_test($ae_free['is_valid'] === TRUE, 'Free action with 0 actions = valid');

$ae_react = $rulesEngine->validateActionEconomy(['actions_remaining' => 0, 'reaction_available' => 1], 'reaction');
assert_test($ae_react['is_valid'] === TRUE, 'Reaction with reaction available = valid');

$ae_react_used = $rulesEngine->validateActionEconomy(['actions_remaining' => 0, 'reaction_available' => 0], 'reaction');
assert_test($ae_react_used['is_valid'] === FALSE, 'Reaction already used = invalid');

$ae_invalid = $rulesEngine->validateActionEconomy(['actions_remaining' => 3], 'fireball');
assert_test($ae_invalid['is_valid'] === FALSE, 'Invalid action cost = invalid');

// --- Action Prerequisites ---
echo "--- Action Prerequisites ---\n";

$prereq_strike = $rulesEngine->validateActionPrerequisites(
  ['entity_ref' => json_encode(['weapon' => 'longsword'])],
  'strike'
);
assert_test($prereq_strike['is_valid'] === TRUE, 'Strike with weapon equipped = valid');

$prereq_no_weapon = $rulesEngine->validateActionPrerequisites(
  ['entity_ref' => json_encode([])],
  'strike'
);
assert_test($prereq_no_weapon['is_valid'] === FALSE, 'Strike without weapon = invalid');

$prereq_spell = $rulesEngine->validateActionPrerequisites(
  ['entity_ref' => json_encode(['spell_slots' => [1 => 3]])],
  ['type' => 'cast_spell', 'spell_level' => 1]
);
assert_test($prereq_spell['is_valid'] === TRUE, 'Cast spell with slots = valid');

$prereq_no_slots = $rulesEngine->validateActionPrerequisites(
  ['entity_ref' => json_encode(['spell_slots' => [1 => 0]])],
  ['type' => 'cast_spell', 'spell_level' => 1]
);
assert_test($prereq_no_slots['is_valid'] === FALSE, 'Cast spell with no slots = invalid');

$prereq_cantrip = $rulesEngine->validateActionPrerequisites(
  ['entity_ref' => json_encode([])],
  ['type' => 'cast_spell', 'spell_level' => 0]
);
assert_test($prereq_cantrip['is_valid'] === TRUE, 'Cantrip (level 0) always valid');

$prereq_shield = $rulesEngine->validateActionPrerequisites(
  ['entity_ref' => json_encode(['shield' => ['hardness' => 3]])],
  'raise_shield'
);
assert_test($prereq_shield['is_valid'] === TRUE, 'Raise shield with shield = valid');

$prereq_no_shield = $rulesEngine->validateActionPrerequisites(
  ['entity_ref' => json_encode([])],
  'raise_shield'
);
assert_test($prereq_no_shield['is_valid'] === FALSE, 'Raise shield without shield = invalid');

$prereq_broken_shield = $rulesEngine->validateActionPrerequisites(
  ['entity_ref' => json_encode(['shield' => ['hardness' => 3, 'broken' => true]])],
  'raise_shield'
);
assert_test($prereq_broken_shield['is_valid'] === FALSE, 'Raise broken shield = invalid');

// --- Immunities ---
echo "--- Immunities ---\n";

$imm_fire = $rulesEngine->checkImmunities(
  ['entity_ref' => json_encode(['immunities' => ['fire']])],
  'fire'
);
assert_test($imm_fire['is_immune'] === TRUE, 'Fire immunity blocks fire');

$imm_no = $rulesEngine->checkImmunities(
  ['entity_ref' => json_encode(['immunities' => ['fire']])],
  'cold'
);
assert_test($imm_no['is_immune'] === FALSE, 'Fire immunity does not block cold');

$imm_undead = $rulesEngine->checkImmunities(
  ['entity_ref' => json_encode(['traits' => ['undead']])],
  'poison'
);
assert_test($imm_undead['is_immune'] === TRUE, 'Undead immune to poison');

$imm_undead_disease = $rulesEngine->checkImmunities(
  ['entity_ref' => json_encode(['traits' => ['undead']])],
  'disease'
);
assert_test($imm_undead_disease['is_immune'] === TRUE, 'Undead immune to disease');

$imm_construct = $rulesEngine->checkImmunities(
  ['entity_ref' => json_encode(['traits' => ['construct']])],
  'bleed'
);
assert_test($imm_construct['is_immune'] === TRUE, 'Construct immune to bleed');

$imm_construct_healing = $rulesEngine->checkImmunities(
  ['entity_ref' => json_encode(['traits' => ['construct']])],
  'healing'
);
assert_test($imm_construct_healing['is_immune'] === TRUE, 'Construct immune to healing');

$imm_none = $rulesEngine->checkImmunities(
  ['entity_ref' => json_encode([])],
  'fire'
);
assert_test($imm_none['is_immune'] === FALSE, 'No immunities = not immune');

// --- Attack Validation ---
echo "--- Attack Validation ---\n";

$atk_valid = $rulesEngine->validateAttack(
  ['id' => 1, 'is_defeated' => 0],
  ['id' => 2, 'is_defeated' => 0],
  ['type' => 'melee', 'range' => 1],
  0
);
assert_test($atk_valid['is_valid'] === TRUE, 'Normal attack validation passes');

$atk_dead_attacker = $rulesEngine->validateAttack(
  ['id' => 1, 'is_defeated' => 1],
  ['id' => 2, 'is_defeated' => 0],
  [],
  0
);
assert_test($atk_dead_attacker['is_valid'] === FALSE, 'Defeated attacker cannot attack');

$atk_dead_target = $rulesEngine->validateAttack(
  ['id' => 1, 'is_defeated' => 0],
  ['id' => 2, 'is_defeated' => 1],
  [],
  0
);
assert_test($atk_dead_target['is_valid'] === FALSE, 'Cannot attack defeated target');

$atk_self = $rulesEngine->validateAttack(
  ['id' => 1, 'is_defeated' => 0],
  ['id' => 1, 'is_defeated' => 0],
  [],
  0
);
assert_test($atk_self['is_valid'] === FALSE, 'Cannot attack self');

// Range check.
$atk_range = $rulesEngine->validateAttack(
  ['id' => 1, 'is_defeated' => 0, 'position_q' => 0, 'position_r' => 0],
  ['id' => 2, 'is_defeated' => 0, 'position_q' => 5, 'position_r' => 0],
  ['type' => 'melee', 'range' => 1],
  0
);
assert_test($atk_range['is_valid'] === FALSE, 'Melee attack at distance 5 = out of range');

$atk_in_range = $rulesEngine->validateAttack(
  ['id' => 1, 'is_defeated' => 0, 'position_q' => 0, 'position_r' => 0],
  ['id' => 2, 'is_defeated' => 0, 'position_q' => 1, 'position_r' => 0],
  ['type' => 'melee', 'range' => 1],
  0
);
assert_test($atk_in_range['is_valid'] === TRUE, 'Melee attack at distance 1 = in range');

// --- Spell Validation ---
echo "--- Spell Validation ---\n";

$spell_valid = $rulesEngine->validateSpellCast(
  ['id' => 1, 'is_defeated' => 0, 'entity_ref' => json_encode(['spell_slots' => [1 => 3]])],
  ['name' => 'Magic Missile', 'range' => 6],
  1,
  [['id' => 2, 'is_defeated' => 0]],
  0
);
assert_test($spell_valid['is_valid'] === TRUE, 'Valid spell cast passes');

$spell_defeated = $rulesEngine->validateSpellCast(
  ['id' => 1, 'is_defeated' => 1],
  [],
  1,
  [],
  0
);
assert_test($spell_defeated['is_valid'] === FALSE, 'Defeated caster cannot cast');

$spell_no_slots = $rulesEngine->validateSpellCast(
  ['id' => 1, 'is_defeated' => 0, 'entity_ref' => json_encode(['spell_slots' => [1 => 0]])],
  [],
  1,
  [],
  0
);
assert_test($spell_no_slots['is_valid'] === FALSE, 'No spell slots = cannot cast');

$spell_cantrip = $rulesEngine->validateSpellCast(
  ['id' => 1, 'is_defeated' => 0, 'entity_ref' => json_encode([])],
  [],
  0,
  [],
  0
);
assert_test($spell_cantrip['is_valid'] === TRUE, 'Cantrip always valid');

$spell_range = $rulesEngine->validateSpellCast(
  ['id' => 1, 'is_defeated' => 0, 'position_q' => 0, 'position_r' => 0, 'entity_ref' => json_encode(['spell_slots' => [1 => 1]])],
  ['name' => 'Touch Spell', 'range' => 1],
  1,
  [['id' => 2, 'is_defeated' => 0, 'position_q' => 5, 'position_r' => 0]],
  0
);
assert_test($spell_range['is_valid'] === FALSE, 'Touch spell at distance 5 = out of range');

$spell_dead_target = $rulesEngine->validateSpellCast(
  ['id' => 1, 'is_defeated' => 0, 'entity_ref' => json_encode(['spell_slots' => [1 => 1]])],
  ['name' => 'Heal', 'range' => 6],
  1,
  [['id' => 2, 'is_defeated' => 1, 'name' => 'Bob']],
  0
);
assert_test($spell_dead_target['is_valid'] === FALSE, 'Cannot target defeated participant');

// =========================================================================
// COMBAT CALCULATOR CROSS-CHECK
// =========================================================================

echo "\n=== CombatCalculator Cross-Check ===\n\n";

echo "--- Attack Bonus ---\n";
$cb_bonus = $combatCalc->calculateAttackBonus([
  'ability_modifier' => 4,
  'proficiency_bonus' => 8,
  'level' => 0,
  'item_bonus' => 1,
]);
assert_test($cb_bonus === 13, 'CombatCalculator: attack bonus = 4+8+0+1 = 13');

echo "--- Spell DC ---\n";
$cb_dc = $combatCalc->calculateSpellSaveDC([
  'ability_modifier' => 5,
  'proficiency_bonus' => 10,
  'level' => 0,
  'item_bonus' => 1,
]);
assert_test($cb_dc === 26, 'CombatCalculator: spell DC = 10+5+10+0+1 = 26');

echo "--- Simple DCs ---\n";
$dc1 = $combatCalc->getSimpleDC(1);
assert_test($dc1 === 15, 'Level 1 DC = 15');
$dc10 = $combatCalc->getSimpleDC(10);
assert_test($dc10 === 27, 'Level 10 DC = 27');
$dc20 = $combatCalc->getSimpleDC(20);
assert_test($dc20 === 40, 'Level 20 DC = 40');

echo "--- Task DCs ---\n";
$td_mod = $combatCalc->getTaskDC('moderate');
assert_test($td_mod === 20, 'Moderate task DC = 20');
$td_ext = $combatCalc->getTaskDC('extreme');
assert_test($td_ext === 30, 'Extreme task DC = 30');

// =========================================================================
// STATE MANAGER TESTS (unit-level, no DB)
// =========================================================================

echo "\n=== StateManager (Transition Validation) ===\n\n";

echo "--- Valid Transitions ---\n";

// Use class constants for validation.
$transitions = \Drupal\dungeoncrawler_content\Service\StateManager::TRANSITIONS;
assert_test(in_array('active', $transitions['pending'], TRUE), 'pending → active is valid');
assert_test(in_array('ended', $transitions['pending'], TRUE), 'pending → ended is valid');
assert_test(in_array('paused', $transitions['active'], TRUE), 'active → paused is valid');
assert_test(in_array('ended', $transitions['active'], TRUE), 'active → ended is valid');
assert_test(in_array('active', $transitions['paused'], TRUE), 'paused → active is valid');
assert_test(empty($transitions['ended']), 'ended → nothing is valid (terminal state)');
assert_test(!in_array('pending', $transitions['active'], TRUE), 'active → pending is NOT valid');

// =========================================================================
// REACTION HANDLER TESTS (unit-level checks)
// =========================================================================

echo "\n=== ReactionHandler (AoO Trait Inference) ===\n\n";

$reactionHandler = \Drupal::service('dungeoncrawler_content.reaction_handler');

// Use reflection to test the protected inferTraitsFromAction method.
$ref = new ReflectionMethod($reactionHandler, 'inferTraitsFromAction');
$ref->setAccessible(TRUE);

$stride_traits = $ref->invoke($reactionHandler, 'stride');
assert_test(in_array('move', $stride_traits, TRUE), 'stride → move trait');

$interact_traits = $ref->invoke($reactionHandler, 'interact');
assert_test(in_array('manipulate', $interact_traits, TRUE), 'interact → manipulate trait');

$cast_traits = $ref->invoke($reactionHandler, 'cast_spell');
assert_test(in_array('manipulate', $cast_traits, TRUE), 'cast_spell → manipulate trait');
assert_test(in_array('concentrate', $cast_traits, TRUE), 'cast_spell → concentrate trait');

// Test hasAttackOfOpportunity via reflection.
$has_aoo = new ReflectionMethod($reactionHandler, 'hasAttackOfOpportunity');
$has_aoo->setAccessible(TRUE);

$fighter = ['entity_ref' => json_encode(['class' => 'fighter'])];
assert_test($has_aoo->invoke($reactionHandler, $fighter) === TRUE, 'Fighter has AoO');

$wizard = ['entity_ref' => json_encode(['class' => 'wizard'])];
assert_test($has_aoo->invoke($reactionHandler, $wizard) === FALSE, 'Wizard does not have AoO');

$monster_aoo = ['entity_ref' => json_encode(['has_aoo' => TRUE])];
assert_test($has_aoo->invoke($reactionHandler, $monster_aoo) === TRUE, 'Monster with has_aoo flag has AoO');

$explicit_aoo = ['entity_ref' => json_encode(['abilities' => ['Attack of Opportunity']])];
assert_test($has_aoo->invoke($reactionHandler, $explicit_aoo) === TRUE, 'Explicit AoO ability detected');

// Test shield detection.
$has_shield = new ReflectionMethod($reactionHandler, 'hasShieldRaised');
$has_shield->setAccessible(TRUE);

$shield_up = ['entity_ref' => json_encode(['shield_raised' => TRUE])];
assert_test($has_shield->invoke($reactionHandler, $shield_up) === TRUE, 'Shield raised detected');

$shield_down = ['entity_ref' => json_encode(['shield_raised' => FALSE])];
assert_test($has_shield->invoke($reactionHandler, $shield_down) === FALSE, 'Shield not raised detected');

// Test shield stats resolution.
$resolve_shield = new ReflectionMethod($reactionHandler, 'resolveShieldStats');
$resolve_shield->setAccessible(TRUE);

$wooden = ['entity_ref' => json_encode(['shield' => ['hardness' => 3, 'hp' => 12, 'bt' => 6]])];
$ws = $resolve_shield->invoke($reactionHandler, $wooden);
assert_test($ws['hardness'] === 3, 'Wooden shield hardness = 3');
assert_test($ws['hp'] === 12, 'Wooden shield HP = 12');
assert_test($ws['bt'] === 6, 'Wooden shield BT = 6');

$steel = ['entity_ref' => json_encode(['shield' => ['hardness' => 5, 'hp' => 20, 'bt' => 10]])];
$ss = $resolve_shield->invoke($reactionHandler, $steel);
assert_test($ss['hardness'] === 5, 'Steel shield hardness = 5');
assert_test($ss['hp'] === 20, 'Steel shield HP = 20');

$default_shield = ['entity_ref' => json_encode([])];
$ds = $resolve_shield->invoke($reactionHandler, $default_shield);
assert_test($ds['hardness'] === 3, 'Default shield hardness = 3');
assert_test($ds['hp'] === 12, 'Default shield HP = 12');
assert_test($ds['bt'] === 6, 'Default shield BT = 6');

// Test hex distance.
$hex_dist = new ReflectionMethod($reactionHandler, 'hexDistance');
$hex_dist->setAccessible(TRUE);

assert_test($hex_dist->invoke($reactionHandler, 0, 0, 0, 0) === 0, 'Hex distance same hex = 0');
assert_test($hex_dist->invoke($reactionHandler, 0, 0, 1, 0) === 1, 'Hex distance adjacent = 1');
assert_test($hex_dist->invoke($reactionHandler, 0, 0, 2, -1) === 2, 'Hex distance 2 away = 2');
assert_test($hex_dist->invoke($reactionHandler, 0, 0, 3, 0) === 3, 'Hex distance 3 straight = 3');

// =========================================================================
// SHIELD BLOCK MATH TEST
// =========================================================================

echo "\n=== Shield Block Math ===\n\n";

// Simulate Shield Block with different damage amounts.
// Shield: hardness 5, HP 20, BT 10.

// Case 1: Damage less than hardness.
echo "--- Low Damage (blocked entirely by hardness) ---\n";
// 3 damage vs hardness 5: blocked = 3, remaining = 0.
$low_blocked = min(5, 3);
$low_remaining = max(0, 3 - 5);
assert_test($low_blocked === 3, 'Low damage: 3 blocked by hardness 5');
assert_test($low_remaining === 0, 'Low damage: 0 remaining');

// Case 2: Damage exceeds hardness.
echo "--- High Damage (partially blocked) ---\n";
$high_blocked = min(5, 12);
$high_remaining = max(0, 12 - 5);
assert_test($high_blocked === 5, 'High damage: 5 blocked by hardness 5');
assert_test($high_remaining === 7, 'High damage: 7 remaining hits both PC and shield');

// Case 3: Shield breaks (HP drops to/below BT).
echo "--- Shield Break Threshold ---\n";
$shield_hp_before = 12;
$shield_bt = 10;
$shield_damage = 7;
$shield_hp_after = $shield_hp_before - $shield_damage;
$broke = $shield_hp_after <= $shield_bt && $shield_hp_before > $shield_bt;
assert_test($broke === TRUE, 'Shield breaks when HP drops from 12 to 5 (below BT 10)');

$shield_hp_before2 = 20;
$shield_damage2 = 5;
$shield_hp_after2 = $shield_hp_before2 - $shield_damage2;
$broke2 = $shield_hp_after2 <= $shield_bt && $shield_hp_before2 > $shield_bt;
assert_test($broke2 === FALSE, 'Shield at HP 15 (above BT 10) does not break');

// =========================================================================
// SUMMARY
// =========================================================================

echo "\n===================================\n";
echo "Passed: {$GLOBALS['tests_passed']}\n";
echo "Failed: {$GLOBALS['tests_failed']}\n";
echo "===================================\n";

if ($GLOBALS['tests_failed'] > 0) {
  echo "SOME TESTS FAILED!\n";
  exit(1);
}
else {
  echo "ALL TESTS PASSED\n";
}
