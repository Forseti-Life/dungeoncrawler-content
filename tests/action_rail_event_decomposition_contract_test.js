/**
 * @file
 * Contract checks for Action Rail event decomposition completion.
 *
 * Run with:
 *   node tests/action_rail_event_decomposition_contract_test.js
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

const actionRailPanelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ActionRailPanel.js'), 'utf8');
const gameShellSource = require('./helpers/js-source.js').readGameShellSource();
const merchantPanelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/MerchantPanel.js'), 'utf8');

console.log('\n=== Action rail event decomposition contract ===');

assert(
  actionRailPanelSource.includes("this.bus.on('room:occupants-membership-changed'")
    && actionRailPanelSource.includes("if (normalizedSource === 'room:occupants-membership-changed') {")
    && actionRailPanelSource.includes("this.invalidateActionRail(['room', 'header']);"),
  'ActionRailPanel consumes room membership events with narrow room/header invalidation semantics'
);

assert(
  gameShellSource.includes("this.bus.emit('room:occupants-membership-changed', {")
    && gameShellSource.includes("shell.bus.emit('room:occupants-decoration-changed', {")
    && gameShellSource.includes("shell.bus.emit('merchant:stock-loaded', {")
    && gameShellSource.includes('occupants: updatedOccupants,'),
  'GameShell emits explicit membership/decoration/merchant events and includes occupants in merchant payloads'
);

assert(
  merchantPanelSource.includes("this.bus.on('room:occupants-membership-changed'")
    && merchantPanelSource.includes("this.bus.on('merchant:stock-loaded', (d) => {"),
  'MerchantPanel consumes decomposed membership and merchant-stock events'
);

console.log('\n==============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
