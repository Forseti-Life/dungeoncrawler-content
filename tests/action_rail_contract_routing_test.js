/**
 * @file
 * Focused regressions for shared Action Rail contract routing.
 *
 * Run with:
 *   node tests/action_rail_contract_routing_test.js
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
const actionRailContractSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/contracts/action-rail-contract.js'), 'utf8');

console.log('\n=== Action rail shared contract routing ===');

assert(
  actionRailContractSource.includes('export const ACTION_RAIL_CATEGORIES = Object.freeze([')
    && actionRailContractSource.includes('navigate')
    && actionRailContractSource.includes('spells')
    && actionRailContractSource.includes('turn')
    && actionRailContractSource.includes('export function resolveActionRailCategory(category = \'\', fallback = \'navigate\') {'),
  'shared contract declares canonical action-rail categories and category resolution'
);

assert(
  actionRailContractSource.includes("navigate: (button) => ({ event: 'user:navigate', payload: { button } })")
    && actionRailContractSource.includes("end_turn: (button, actionType) => ({ event: 'user:end-turn', payload: { button, actionType } })")
    && actionRailContractSource.includes("choose_not_to_act: (button, actionType) => ({ event: 'user:end-turn', payload: { button, actionType } })")
    && actionRailContractSource.includes('export function getActionRailDirectRoute(actionType, button) {')
    && actionRailContractSource.includes('export function isActionRailSelectableAction(actionType) {'),
  'shared contract declares direct-route and selectable-action routing policies'
);

assert(
  actionRailPanelSource.includes("from '../contracts/action-rail-contract.js';")
    && actionRailPanelSource.includes('const directRoute = getActionRailDirectRoute(actionType, button);')
    && actionRailPanelSource.includes('if (isActionRailSelectableAction(actionType)) {')
    && actionRailPanelSource.includes("console.warn('[ActionRailPanel] Unsupported panel action:', actionType);")
    && actionRailPanelSource.includes('resolveActionRailCategory(this.activeActionRailCategory, \'navigate\')'),
  'ActionRailPanel consumes shared contract helpers for category resolution and action routing'
);

assert(
  actionRailPanelSource.includes("getServerActionIdForExecute('search')")
    && actionRailPanelSource.includes("getServerActionIdForExecute('spell')")
    && actionRailPanelSource.includes("getServerActionIdForExecute('consumable')")
    && actionRailPanelSource.includes("getServerActionIdForExecute('skill')")
    && actionRailPanelSource.includes("getServerActionIdForExecute('feat')"),
  'ActionRailPanel server availability gating uses shared execute->server-action mapping'
);

console.log('\n==========================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
