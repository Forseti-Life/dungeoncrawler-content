/**
 * @file
 * Contract test: movement tab labeling + drag-first movement controls.
 *
 * Run with:
 *   node tests/action_rail_movement_tab_contract_test.js
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
const navigatePanelServiceSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/services/action-rail-navigate-panel-service.js'), 'utf8');

console.log('\n=== Action rail movement tab contracts ===');

assert(
  templateSource.includes('data-action-rail-category="navigate">{{ \'Movement\'|t }}'),
  'Action Rail category label is renamed from Navigate to Movement'
);

assert(
  navigatePanelServiceSource.includes("title: 'Movement'")
    && !navigatePanelServiceSource.includes("buildInRoomMovementActions(panel, context)")
    && !navigatePanelServiceSource.includes("execute: 'stride'")
    && !navigatePanelServiceSource.includes("execute: 'step'"),
  'movement tab service no longer renders in-bar stride/step controls'
);

console.log('\n=========================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
