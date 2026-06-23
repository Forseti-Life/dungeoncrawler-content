/**
 * @file
 * Focused contract test for ActionRailPanel architecture boundaries.
 *
 * Run with:
 *   node tests/action_rail_panel_test.js
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

const panelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ActionRailPanel.js'), 'utf8');
const contractSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/contracts/action-rail-contract.js'), 'utf8');
const contextServiceSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/services/action-rail-context-service.js'), 'utf8');

console.log('\n=== ActionRailPanel architecture contracts ===');

assert(
  panelSource.includes("import { buildActionRailContext } from '../services/action-rail-context-service.js';")
    && panelSource.includes('getActionRailContext() {')
    && panelSource.includes('return buildActionRailContext(this.stateManager);')
    && contextServiceSource.includes('const phaseSnapshot = hexmap?.gameCoordinator?.phaseManager?.getSnapshot?.() || {};')
    && contextServiceSource.includes('availableActions: Array.isArray(phaseSnapshot?.availableActions) ? phaseSnapshot.availableActions : [],')
    && contextServiceSource.includes('actionContract: phaseSnapshot?.actionContract || null,'),
  'ActionRailPanel delegates coordinator-driven context assembly to shared context service'
);

assert(
  panelSource.includes('isServerActionAvailable(context, actionId) {')
    && panelSource.includes('const actions = Array.isArray(context?.actionContract?.actions) ? context.actionContract.actions : [];')
    && panelSource.includes('return action ? action.available !== false : false;'),
  'ActionRailPanel resolves server action availability from canonical action contract definitions'
);

assert(
  panelSource.includes("const searchAvailable = this.isServerActionAvailable(context, getServerActionIdForExecute('search'));")
    && panelSource.includes("const spellActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, getServerActionIdForExecute('spell'));")
    && panelSource.includes("const consumeActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, getServerActionIdForExecute('consumable'));")
    && panelSource.includes("const skillActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, getServerActionIdForExecute('skill'));")
    && panelSource.includes("const featActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, getServerActionIdForExecute('feat'));"),
  'category panels gate execution using shared execute-key to server-action mapping'
);

assert(
  panelSource.includes("import { buildNavigateActionRailPanel } from '../services/action-rail-navigate-panel-service.js';")
    && panelSource.includes('navigate: () => buildNavigateActionRailPanel(this, context),')
    && !panelSource.includes("import { fetchVisitedNavigateLocationGroups } from '../services/navigate-location-service.js';")
    && !panelSource.includes('fetch(`/api/campaign/${campaignId}/visited-locations`'),
  'ActionRailPanel delegates navigation preload/rendering to dedicated navigate panel service'
);

assert(
  contractSource.includes('export function getActionRailDirectRoute(actionType, button) {')
    && contractSource.includes('export function isActionRailSelectableAction(actionType) {')
    && panelSource.includes('const directRoute = getActionRailDirectRoute(actionType, button);')
    && panelSource.includes('if (isActionRailSelectableAction(actionType)) {'),
  'ActionRailPanel dispatch boundaries are driven by shared routing contracts'
);

console.log('\n=============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
