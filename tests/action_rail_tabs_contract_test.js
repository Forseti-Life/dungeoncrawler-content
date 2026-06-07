/**
 * @file
 * Contract test: Action Rail tab surface + routing boundaries.
 *
 * Run with:
 *   node tests/action_rail_tabs_contract_test.js
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

const templateSource = fs.readFileSync(path.resolve(__dirname, '../templates/hexmap-v2.html.twig'), 'utf8');
const panelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ActionRailPanel.js'), 'utf8');
const contractSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/contracts/action-rail-contract.js'), 'utf8');

console.log('\n=== Action rail tab + route contracts ===');

assert(
  templateSource.includes('role="tablist"')
    && templateSource.includes('data-action-rail-category="navigate"')
    && templateSource.includes('data-action-rail-category="search"')
    && templateSource.includes('data-action-rail-category="rest"')
    && templateSource.includes('data-action-rail-category="spells"')
    && templateSource.includes('data-action-rail-category="consumables"')
    && templateSource.includes('data-action-rail-category="skills"')
    && templateSource.includes('data-action-rail-category="feats"')
    && templateSource.includes('data-action-rail-category="turn"'),
  'template exposes the canonical tab-driven Action Rail category surface'
);

assert(
  contractSource.includes('export const ACTION_RAIL_CATEGORIES = Object.freeze([')
    && contractSource.includes('navigate')
    && contractSource.includes('search')
    && contractSource.includes('rest')
    && contractSource.includes('spells')
    && contractSource.includes('consumables')
    && contractSource.includes('skills')
    && contractSource.includes('feats')
    && contractSource.includes('turn'),
  'shared contract declares canonical category IDs used by the panel'
);

assert(
  panelSource.includes('bindActionRailDomListener(target, type, handler, options = undefined) {')
    && panelSource.includes('teardownActionRailDomListeners() {')
    && !panelSource.includes('dataset.bound'),
  'panel listener lifecycle is explicit and no longer uses dataset-bound sentinels'
);

assert(
  panelSource.includes('setActiveActionRailCategory(category, { refresh = true, focus = false } = {}) {')
    && panelSource.includes('this.activeActionRailCategory = resolvedCategory;')
    && panelSource.includes('this.resolveActionRailCategory(this.activeActionRailCategory)'),
  'tab-state transitions run through canonical category resolver/setter methods'
);

assert(
  panelSource.includes('const directRoute = getActionRailDirectRoute(actionType, button);')
    && panelSource.includes('if (isActionRailSelectableAction(actionType)) {')
    && panelSource.includes("this.bus.emit('user:action-selected', { actionKey: actionType, button });")
    && panelSource.includes("console.warn('[ActionRailPanel] Unsupported panel action:', actionType);"),
  'panel action routing follows direct-route mapping, selectable whitelist, and unknown-action guard'
);

console.log('\n===========================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
