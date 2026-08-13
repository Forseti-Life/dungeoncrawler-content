/**
 * @file
 * Contract test: party occupant sync must not overwrite canonical placements.
 *
 * Run with:
 *   node tests/game_shell_party_placement_sync_contract_test.js
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

const gameShellSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/GameShell.js'), 'utf8');

console.log('\n=== GameShell party placement sync contracts ===');

assert(
  gameShellSource.includes('_synchronizePartyOccupantsToRoom(roomId) {')
    && gameShellSource.includes('const hasExistingPlacement = Number.isFinite(existingQ) && Number.isFinite(existingR);')
    && gameShellSource.includes('q: hasExistingPlacement ? existingQ : (anchorQ + offset.q),')
    && gameShellSource.includes('r: hasExistingPlacement ? existingR : (anchorR + offset.r),'),
  'party occupant sync preserves canonical placement coordinates when already provided by runtime hydration'
);

console.log('\n===============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
