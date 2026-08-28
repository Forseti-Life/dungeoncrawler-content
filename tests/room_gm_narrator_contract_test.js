/**
 * @file
 * Regression coverage for visible GM label vs narrator turn-role contract.
 *
 * Run with:
 *   node tests/room_gm_narrator_contract_test.js
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

const source = require('./helpers/php-source.js').readGmPipelineSource();

console.log('\n=== Room GM label vs narrator role contract ===');

assert(
  source.includes("$build_encounter_prefix_for_speaker($dungeon_data, 'Narrator')"),
  'visible room narration still uses the Narrator encounter-role prefix'
);
assert(
  source.includes("'speaker' => 'Game Master',\n      'message' => $visible_gm_narrative,\n      'type' => 'npc'"),
  'legacy room chat keeps Game Master as the visible narration label'
);
assert(
  source.includes("'Game Master',\n        'gm',"),
  'normalized room session bridge keeps Game Master as the visible narration label'
);
assert(
  source.includes("'speaker' => 'Game Master'"),
  'GM room response payload still advertises Game Master as the visible speaker'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
