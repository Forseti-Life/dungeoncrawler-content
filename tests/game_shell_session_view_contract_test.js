/**
 * @file
 * Contract checks for GameShell session-view request handling.
 *
 * Run with:
 *   node tests/game_shell_session_view_contract_test.js
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

const source = fs.readFileSync(path.resolve(__dirname, '../js/v2/GameShell.js'), 'utf8');

console.log('\n=== GameShell session-view contract ===');

assert(
  source.includes("this.bus.on('user:session-view-requested', ({ view, options } = {}) => {")
    && source.includes('void this.fetchSessionViewData(view, options ?? {}).then((data) => {')
    && source.includes("this.bus.emit('session:view-data', { view, data });"),
  'session-view request relay uses fetchSessionViewData and emits canonical session:view-data payload'
);

assert(
  source.includes('async fetchSessionViewData(view, options = {}) {')
    && source.includes("const chatPanel = this.panels?.chat || null;")
    && source.includes("if (!chatPanel || typeof chatPanel.fetchSessionViewData !== 'function') {")
    && source.includes("throw new Error('ChatPanel session view data adapter unavailable.');")
    && source.includes('return chatPanel.fetchSessionViewData(view, options);'),
  'GameShell exposes a concrete fetchSessionViewData adapter backed by ChatPanel'
);

console.log('\n=======================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
