/**
 * @file
 * Contract checks for Action Rail fingerprint skip gating and telemetry.
 *
 * Run with:
 *   node tests/action_rail_refresh_telemetry_contract_test.js
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

const panelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ActionRailPanel.js'), 'utf8');

console.log('\n=== Action rail refresh telemetry contract ===');

assert(
  panelSource.includes('isActionRailDebugEnabled() {')
    && panelSource.includes("window.localStorage.getItem('dc:debug:action-rail') === '1'")
    && panelSource.includes('emitActionRailTelemetry(payload = {}) {')
    && panelSource.includes("this.bus?.emit?.('action-rail:telemetry', payload);"),
  'ActionRailPanel provides debug-gated telemetry emission with bus payload export'
);

assert(
  panelSource.includes('const didSwitchCategory = this._lastActionRailBodyCategory !== categoryKey;')
    && panelSource.includes("if (!didSwitchCategory && !fingerprintChanged) {")
    && panelSource.includes("bodyReason = 'fingerprint-unchanged-skip';")
    && panelSource.includes('this._actionRailMetrics.bodySkips += 1;'),
  'ActionRailPanel enforces hard body-render skip when active-category fingerprint is unchanged'
);

console.log('\n==============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');

