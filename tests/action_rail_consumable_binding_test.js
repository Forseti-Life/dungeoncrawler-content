/**
 * @file
 * Focused regressions for Action Rail consumable wiring.
 *
 * Run with:
 *   node tests/action_rail_consumable_binding_test.js
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
const encounterSystemSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/EncounterSystem.js'), 'utf8');
const encounterPhaseSource = fs.readFileSync(path.resolve(__dirname, '../src/Service/EncounterPhaseHandler.php'), 'utf8');

console.log('\n=== Action rail Consumable bindings ===');

assert(
  actionRailPanelSource.includes("execute: 'consume_item'")
    && actionRailPanelSource.includes("const actionType = String(button.dataset.actionRailExecute || '').trim();")
    && actionRailPanelSource.includes('if (isActionRailSelectableAction(actionType)) {')
    && actionRailPanelSource.includes("this.bus.emit('user:action-selected', { actionKey: actionType, button });")
    && actionRailContractSource.includes("'consume_item',"),
  'action rail emits consumable entries through the canonical action-selected bus contract'
);

assert(
  actionRailContractSource.includes("consumable: 'executeDirectConsumable'")
    && encounterSystemSource.includes("import { ACTION_SELECTION_HANDLERS, isRestActivityActionKey } from '../contracts/action-rail-contract.js';")
    && encounterSystemSource.includes('const handlerName = ACTION_SELECTION_HANDLERS[key] ||')
    && encounterSystemSource.includes('this[handlerName](d?.button);')
    && encounterSystemSource.includes('async executeDirectConsumable(button) {')
    && encounterSystemSource.includes("_sendCoordinatorActionWithResync(coordinator, 'consume_item', actorRef, {")
    && encounterSystemSource.includes('coordinator.applyAuthoritativeUpdate?.(result);'),
  'encounter system handles consumable selections through the canonical coordinator consume_item intent path'
);

assert(
  !encounterSystemSource.includes('`/api/character/${characterId}/inventory`')
    && encounterSystemSource.includes('Consumable actions require authoritative coordinator state and an active actor.')
    && encounterSystemSource.includes('hexmap.loadCharacterFromApi(characterId);'),
  'consumable handler removes legacy inventory fallback and enforces authoritative coordinator execution'
);

assert(
  encounterPhaseSource.includes("'consume_item',"),
  'server encounter phase action list includes consume_item intent'
);

console.log('\n========================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
