/**
 * @file
 * Contract test: encounter talk lane must forward combat-entry projections.
 *
 * Run with:
 *   node tests/encounter_talk_combat_entry_projection_contract_test.js
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
  path.resolve(__dirname, '../src/Service/EncounterActionExecutor.php'),
  'utf8',
);

console.log('\n=== Encounter talk combat-entry projection contract ===');

assert(
  source.includes("'aggression_summary' => $chat_result['aggression_summary'] ?? NULL,")
    && source.includes("'combat_entry_summary' => $chat_result['combat_entry_summary'] ?? NULL,"),
  'Encounter talk chat response preserves aggression/combat entry summaries from room chat output'
);

assert(
  source.includes("'aggression_summary' => $chat_response['aggression_summary'],")
    && source.includes("'combat_entry_summary' => $chat_response['combat_entry_summary'],"),
  'Encounter talk result forwards projection summaries to encounter consumers'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}

