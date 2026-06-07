/**
 * @file
 * Focused regressions for Action Rail context service boundaries.
 *
 * Run with:
 *   node tests/action_rail_context_service_contract_test.js
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

const contextServiceSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/services/action-rail-context-service.js'), 'utf8');
const panelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ActionRailPanel.js'), 'utf8');

console.log('\n=== Action rail context service contract ===');

assert(
  contextServiceSource.includes('export function buildActionRailContext(stateManager) {')
    && contextServiceSource.includes('const hexmap = stateManager?.hexmap || null;')
    && contextServiceSource.includes('const selected = stateManager?.get?.(\'selectedEntity\') || null;'),
  'context service owns canonical context construction from state manager + hexmap'
);

assert(
  contextServiceSource.includes('const phaseSnapshot = hexmap?.gameCoordinator?.phaseManager?.getSnapshot?.() || {};')
    && contextServiceSource.includes('availableActions: Array.isArray(phaseSnapshot?.availableActions) ? phaseSnapshot.availableActions : [],')
    && contextServiceSource.includes('actionContract: phaseSnapshot?.actionContract || null,')
    && contextServiceSource.includes('statusLabel: buildActionRailEntrySummary(['),
  'context service carries phase/availability contract fields and status synthesis'
);

assert(
  panelSource.includes("import { buildActionRailContext } from '../services/action-rail-context-service.js';")
    && panelSource.includes('return buildActionRailContext(this.stateManager);')
    && !panelSource.includes('const phaseSnapshot = hexmap?.gameCoordinator?.phaseManager?.getSnapshot?.() || {};'),
  'ActionRailPanel consumes the context service and no longer duplicates context assembly internals'
);

console.log('\n============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
