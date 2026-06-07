/**
 * @file
 * Focused regressions for Action Rail DOM lifecycle management.
 *
 * Run with:
 *   node tests/action_rail_lifecycle_contract_test.js
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

console.log('\n=== Action rail lifecycle contract ===');

assert(
  actionRailPanelSource.includes('this._domListeners = [];')
    && actionRailPanelSource.includes('bindActionRailDomListener(target, type, handler, options = undefined) {')
    && actionRailPanelSource.includes('this._domListeners.push(() => {')
    && actionRailPanelSource.includes('teardownActionRailDomListeners() {')
    && actionRailPanelSource.includes('this._domListeners.forEach((unbind) => unbind());'),
  'ActionRailPanel tracks DOM listeners through explicit bind/unbind lifecycle methods'
);

assert(
  actionRailPanelSource.includes('this.teardownActionRailDomListeners();')
    && actionRailPanelSource.includes('destroy() {')
    && actionRailPanelSource.includes('setupActionRail() {')
    && actionRailPanelSource.includes('this.bindActionRailDomListener(categories, \'click\', (event) => {')
    && actionRailPanelSource.includes('this.bindActionRailDomListener(categories, \'keydown\', (event) => {')
    && actionRailPanelSource.includes('this.bindActionRailDomListener(panelBody, \'click\', (event) => {'),
  'ActionRailPanel setup/destroy paths are lifecycle-safe and avoid dataset-bound listener sentinels'
);

assert(
  actionRailPanelSource.includes('resolveActionRailCategory(category = \'\') {')
    && actionRailPanelSource.includes('return resolveContractActionRailCategory(category, \'navigate\');')
    && actionRailPanelSource.includes('setActiveActionRailCategory(category, { refresh = true, focus = false } = {}) {')
    && actionRailPanelSource.includes('this.activeActionRailCategory = resolvedCategory;'),
  'ActionRailPanel uses canonical category setter/resolver methods for tab-state transitions'
);

console.log('\n======================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
