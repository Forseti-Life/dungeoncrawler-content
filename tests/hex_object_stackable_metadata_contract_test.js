/**
 * @file
 * Contract test: projected hex objects must carry stackable metadata for hover inspection.
 *
 * Run with:
 *   node tests/hex_object_stackable_metadata_contract_test.js
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

const source = require('./helpers/js-source.js').readGameShellSource();

console.log('\n=== Hex object stackable metadata contract ===');

assert(
  source.includes("stackable: typeof object?.stackable === 'boolean' ? object.stackable : Boolean(definition?.stackable)"),
  'Hex-object blueprint state.metadata includes stackable'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
