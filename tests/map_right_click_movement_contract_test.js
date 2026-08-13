/**
 * @file
 * Contract test: map right-click movement context flow.
 *
 * Run with:
 *   node tests/map_right_click_movement_contract_test.js
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

const shellSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/GameShell.js'), 'utf8');
const canvasSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/canvas/HexCanvas.js'), 'utf8');
const inputSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/canvas/HexInputHandler.js'), 'utf8');
const encounterSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/EncounterSystem.js'), 'utf8');

console.log('\n=== Map right-click movement contracts ===');

assert(
  shellSource.includes("if (Number(button) === 2) {")
    && shellSource.includes('this._showHexContextMenuForHex(Number(q), Number(r), {')
    && shellSource.includes('hasOccupants: hexEntities.length > 0,'),
  'GameShell right-click branch routes hex selection to context-sensitive menu flow'
);

assert(
  shellSource.includes('_resolveHexMovementOption(actionType, q, r) {')
    && shellSource.includes('const liveEncounterActive = Number(snapshot?.encounterId || 0) > 0;')
    && shellSource.includes("const selectedActor = this._getStateValue('selectedEntity') || null;")
    && shellSource.includes('const actor = this.canDragEntityOnMap(selectedActor)')
    && shellSource.includes('movementSystem.findPath(')
    && shellSource.includes('movement?.movementSpeed ?? movement?.movementRemaining')
    && shellSource.includes("reason: 'No reachable path within current movement range.'"),
  'movement context options use pathfinder + room-scene speed fallback checks'
);

assert(
  shellSource.includes('buildCombatDragMovementPlan(entity, targetQ, targetR) {')
    && shellSource.includes('const hexCost = 5;')
    && shellSource.includes('const actionCost = Math.ceil(distanceFt / movementSpeed);')
    && shellSource.includes("actionType: 'stride'"),
  'combat drag movement converts dropped hex distance into 5ft stride action economy'
);

assert(
  shellSource.includes('_executeHexMovementAction(actionType, q, r) {')
    && shellSource.includes('if (this.isLiveCombatEncounterActive()) {')
    && shellSource.includes("await encounterSystem.executeDirectMovementAction(actionType, button);")
    && shellSource.includes('await this.moveEntityWithinRoom(actor, roomId, Number(q), Number(r));')
    && encounterSource.includes('const selectedEntity = context?.selectedEntity || null;')
    && encounterSource.includes('const selectedIsControllable = Boolean(')
    && encounterSource.includes('const actorRef = String(context.actorRef || \'\').trim() || (selectedIsControllable ? selectedActorRef : \'\') || null;'),
  'context menu movement dispatch routes combat through encounter actions and non-combat through direct room movement'
);

assert(
  canvasSource.includes("this.bus.emit('canvas:hex-clicked', {")
    && canvasSource.includes('clientX:')
    && canvasSource.includes('clientY:')
    && inputSource.includes("this.bus.on('canvas:hex-clicked', ({ q, r, button = 0, clientX = null, clientY = null } = {}) => {")
    && inputSource.includes('clientX,')
    && inputSource.includes('clientY,'),
  'pointer coordinates are propagated from canvas to hex click events for menu placement'
);

console.log('\n=========================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
