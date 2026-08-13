/**
 * @file
 * Contract test: encounter action availability policy.
 *
 * Run with:
 *   node tests/actor_action_availability_policy_contract_test.js
 */

const fs = require('fs');
const path = require('path');

let passed = 0;
let failed = 0;

function assert(condition, message) {
  if (condition) {
    passed++;
    console.log(`  ✓ ${message}`);
  } else {
    failed++;
    console.error(`  ✗ ${message}`);
  }
}

const source = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ActorActionAvailabilityService.php'),
  'utf8',
);

console.log('\n=== Actor action availability policy contract ===');

assert(
  source.includes('$combat_active = !$room_scene;')
    && source.includes('$can_use_turn_actions = !$combat_active || $is_active_turn_actor;')
    && source.includes('if (!$can_use_turn_actions) {'),
  'turn-required actions are gated only when combat is active and actor is out-of-turn'
);

assert(
  source.includes('$has_single_action_budget = !$combat_active || $actions_remaining >= 1;')
    && source.includes('if (!$combat_active || $actions_remaining >= 2) {')
    && source.includes("'request'")
    && source.includes("'sense_motive'")
    && source.includes("'perform'")
    && source.includes("'command_animal'")
    && source.includes("'cast_spell'")
    && source.includes("'feint'")
    && source.includes("'administer_first_aid'"),
  'room-scene mode keeps common social, support, and spell actions available when budget allows'
);

assert(
  source.includes("bool $enforce_turn_gating = TRUE")
    && source.includes('if ($enforce_turn_gating && (!$is_active_turn_actor || $actions_remaining <= 0)) {'),
  'high-option family availability can bypass turn gating outside combat'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
