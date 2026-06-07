/**
 * @file
 * End-to-end Action Rail bus flow contract checks.
 *
 * Run with:
 *   node tests/action_rail_bus_flow_contract_test.js
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

const contractSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/contracts/action-rail-contract.js'), 'utf8');
const panelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ActionRailPanel.js'), 'utf8');
const encounterSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/EncounterSystem.js'), 'utf8');
const navigationSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/NavigationSystem.js'), 'utf8');

console.log('\n=== Action rail bus flow contract ===');

assert(
  contractSource.includes("navigate: (button) => ({ event: 'user:navigate', payload: { button } })")
    && contractSource.includes("end_turn: (button, actionType) => ({ event: 'user:end-turn', payload: { button, actionType } })")
    && contractSource.includes("choose_not_to_act: (button, actionType) => ({ event: 'user:end-turn', payload: { button, actionType } })"),
  'contract maps direct execute keys to canonical bus events for navigate and end-turn actions'
);

assert(
  panelSource.includes('const directRoute = getActionRailDirectRoute(actionType, button);')
    && panelSource.includes('this.bus.emit(directRoute.event, directRoute.payload || {});')
    && panelSource.includes("this.bus.emit('user:action-selected', { actionKey: actionType, button });"),
  'ActionRailPanel emits direct-route and selectable actions through the shared bus contract'
);

assert(
  encounterSource.includes("this.bus.on('user:action-selected', (d) => {")
    && encounterSource.includes('const handlerName = ACTION_SELECTION_HANDLERS[key] || \'\';')
    && encounterSource.includes("this.bus.on('user:end-turn',     (d) => this.endCurrentTurn(d)),"),
  'EncounterSystem subscribes to canonical action-selected and end-turn bus events'
);

assert(
  contractSource.includes("attack: 'executeDirectAttack'")
    && contractSource.includes("spell: 'executeDirectSpell'")
    && contractSource.includes("search: 'executeDirectSearch'")
    && contractSource.includes("skill: 'executeDirectSkill'")
    && contractSource.includes("feat: 'executeDirectFeat'")
    && contractSource.includes("consumable: 'executeDirectConsumable'"),
  'selection handlers are defined for all direct encounter execution categories'
);

assert(
  navigationSource.includes("this.bus.on('user:navigate', (d) => this.executeDirectNavigate(d?.button))")
    && navigationSource.includes('async executeDirectNavigate(button) {'),
  'NavigationSystem is the sole subscriber for direct navigate bus execution'
);

console.log('\n=====================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
