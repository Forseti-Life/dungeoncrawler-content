/**
 * @file
 * Focused regressions for Action Rail feat wiring.
 *
 * Run with:
 *   node tests/action_rail_feat_binding_test.js
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

console.log('\n=== Action rail Feat bindings ===');

assert(
  actionRailPanelSource.includes("execute: 'feat'")
    && actionRailPanelSource.includes("const actionType = button.dataset.actionRailExecute || '';")
    && actionRailPanelSource.includes("this.bus.emit('user:action-selected', { actionKey: actionType, button });")
    && actionRailPanelSource.includes("'feat',"),
  'action rail emits feat entries through the canonical action-selected bus contract'
);

assert(
  encounterSystemSource.includes("case 'feat':")
    && encounterSystemSource.includes('this.executeDirectFeat(d?.button);')
    && encounterSystemSource.includes('async executeDirectFeat(button) {')
    && encounterSystemSource.includes("_sendCoordinatorActionWithResync(coordinator, 'feat', context.actorRef, {")
    && encounterSystemSource.includes("feat_id: button.dataset.featId || ''")
    && encounterSystemSource.includes('feat_name: featName'),
  'encounter system handles feat action selections and dispatches coordinator feat intents'
);

assert(
  encounterSystemSource.includes("actionType: 'feat'")
    && encounterSystemSource.includes('context.hexmap?.loadCharacterFromApi(context.characterId);'),
  'feat handler preserves non-encounter fallback parity and refreshes character state'
);

assert(
  encounterPhaseSource.includes("'feat',")
    && encounterPhaseSource.includes("case 'feat':")
    && encounterPhaseSource.includes("'summary' => sprintf('%s uses %s.', $actor_name, $feat_name)")
    && encounterPhaseSource.includes("'feat_id' => $feat_id"),
  'server encounter phase accepts feat intents and returns authoritative feat summaries/events'
);

console.log('\n===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
