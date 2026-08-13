/**
 * @file
 * Contract checks for navigation transition sequencing.
 *
 * Run with:
 *   node tests/navigation_transition_sequencing_contract_test.js
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
  path.resolve(__dirname, '../js/v2/systems/NavigationSystem.js'),
  'utf8'
);

console.log('\n=== Navigation transition sequencing contract ===');

const receiptSetActiveRoomIndex = source.indexOf('hexmap.setActiveRoom(targetRoomId);');
const receiptEmitIndex = source.indexOf("source: 'navigation-receipt'");
assert(
  receiptSetActiveRoomIndex !== -1
    && receiptEmitIndex !== -1
    && receiptSetActiveRoomIndex < receiptEmitIndex,
  'authoritative navigation receipt emits capabilities after active-room switch'
);

const fallbackSetActiveRoomIndex = source.indexOf("phase: 'navigation-authoritative'");
const fallbackEmitIndex = source.indexOf("source: 'transition-result-fallback'");
assert(
  fallbackSetActiveRoomIndex !== -1
    && fallbackEmitIndex !== -1
    && fallbackSetActiveRoomIndex < fallbackEmitIndex,
  'fallback navigation result emits capabilities after active-room synchronization'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
