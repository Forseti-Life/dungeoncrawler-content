/**
 * @file
 * Focused regressions for action-rail Search bindings.
 *
 * Run with:
 *   node tests/action_rail_search_binding_test.js
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

const hexmapSource = fs.readFileSync(path.resolve(__dirname, '../js/hexmap.js'), 'utf8');
const actionRailPanelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ActionRailPanel.js'), 'utf8');

console.log('\n=== Action rail Search bindings ===');
assert(
  hexmapSource.includes('handleActionRailDirectAction(actionKey, button = null)')
    && hexmapSource.includes("if (actionKey === 'search') {\n        this.executeDirectSearch(button);"),
  'legacy action rail Search direct action executes the shared search handler'
);
assert(
  hexmapSource.includes("const actionSearchBtn = document.getElementById('action-search');")
    && hexmapSource.includes("addTrackedListener(actionSearchBtn, 'click', async function ()"),
  'standalone #action-search button has a click binding'
);
assert(
  hexmapSource.includes('const canSearchExplorationRoom = Boolean(')
    && hexmapSource.includes('const canInteract = canAct || canSearchExplorationRoom;'),
  'standalone Search remains enabled for active exploration-room actors without combat turns'
);
assert(
  actionRailPanelSource.includes('handleActionRailDirectAction(actionKey, button = null)')
    && actionRailPanelSource.includes("this.bus.emit('user:action-selected', { actionKey, button });"),
  'v2 action rail Search emits the clicked button to the action executor'
);

console.log('\n===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
