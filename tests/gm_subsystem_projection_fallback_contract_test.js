/**
 * @file
 * Contract test: GM subsystem non-actor-scoped fallback projection fields.
 *
 * Run with:
 *   node tests/gm_subsystem_projection_fallback_contract_test.js
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
  path.resolve(__dirname, '../src/Service/GameMasterSubsystemService.php'),
  'utf8',
);

console.log('\n=== GM subsystem projection fallback contract ===');

assert(
  source.includes("if (!array_key_exists('aggression_summary', $result)) {")
    && source.includes("$result['aggression_summary'] = is_array($actor_response['aggression_summary'] ?? NULL)")
    && source.includes("if (!array_key_exists('combat_entry_summary', $result)) {")
    && source.includes("foreach (['aggression_state', 'disposition_state', 'resolved_disposition_by_target', 'relationship_attitudes', 'stance_state'] as $state_slice_key) {")
    && source.includes("if (!array_key_exists('resolved_actor_context', $result)) {"),
  'GameMasterSubsystemService forwards aggression/combat entry summaries and resolved disposition slices in non-actor-scoped responses when missing'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
