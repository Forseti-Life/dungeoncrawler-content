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
const encounterSystemSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/EncounterSystem.js'), 'utf8');
const encounterPhaseSource = fs.readFileSync(path.resolve(__dirname, '../src/Service/EncounterPhaseHandler.php'), 'utf8');

console.log('\n=== Action rail Consumable bindings ===');

assert(
  actionRailPanelSource.includes("execute: 'consumable'")
    && actionRailPanelSource.includes("const actionType = button.dataset.actionRailExecute || '';")
    && actionRailPanelSource.includes("this.bus.emit('user:action-selected', { actionKey: actionType, button });")
    && actionRailPanelSource.includes("'consumable',"),
  'action rail emits consumable entries through the canonical action-selected bus contract'
);

assert(
  encounterSystemSource.includes("consumable: 'executeDirectConsumable'")
    && encounterSystemSource.includes('const handlerName = ACTION_SELECTION_HANDLERS[key] ||')
    && encounterSystemSource.includes('this[handlerName](d?.button);')
    && encounterSystemSource.includes('async executeDirectConsumable(button) {')
    && encounterSystemSource.includes('extractConsumableItems(')
    && encounterSystemSource.includes("_sendCoordinatorActionWithResync(coordinator, 'consume_item', context.actorRef, {")
    && encounterSystemSource.includes('action: \'consume\''),
  'encounter system handles consumable selections and dispatches canonical consume_item intents'
);

assert(
  encounterSystemSource.includes('`/api/character/${characterId}/inventory`')
    && encounterSystemSource.includes('hexmap.loadCharacterFromApi(characterId);'),
  'consumable handler preserves non-encounter fallback parity and refreshes character state'
);

assert(
  encounterPhaseSource.includes("'consume_item',")
    && encounterPhaseSource.includes("case 'consume_item':")
    && encounterPhaseSource.includes("'consume_item requires params.character_id and params.item.'")
    && encounterPhaseSource.includes("GameEventLogger::buildEvent('consume_item', 'encounter', $actor_id, ["),
  'server encounter phase accepts consume_item intents and returns authoritative consume events'
);

console.log('\n========================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
