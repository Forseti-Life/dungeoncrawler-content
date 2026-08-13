/**
 * @file
 * Guards phase-manager action contract preservation when later payloads omit it.
 *
 * Run with:
 *   node tests/action_contract_preservation_contract_test.js
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

const phaseManagerSource = fs.readFileSync(path.resolve(__dirname, '../js/game-coordinator/PhaseManager.js'), 'utf8');

console.log('\n=== Action contract preservation contract ===');

assert(
  phaseManagerSource.includes('this.actionContract = actionContract ?? this.actionContract;'),
  'PhaseManager preserves the last authoritative action contract when a later payload omits one'
);

console.log('\n============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
