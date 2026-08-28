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
    && contextServiceSource.includes('export function selectRailHexmap(stateManager) {')
    && contextServiceSource.includes('export function selectRailSelectedEntity(stateManager) {')
    && contextServiceSource.includes('export function selectRailCanonicalActorByRef(hexmap, actorRef) {')
    && contextServiceSource.includes('export function selectRailRuntimeGameState(hexmap) {')
    && contextServiceSource.includes('const hexmap = selectRailHexmap(stateManager);')
    && contextServiceSource.includes('const selected = selectRailSelectedEntity(stateManager);')
    && contextServiceSource.includes('const runtimeGameState = selectRailRuntimeGameState(hexmap);'),
  'context service owns canonical context construction from state manager + hexmap selector helpers'
);

assert(
  contextServiceSource.includes('export function selectRailPhaseSnapshot(hexmap) {')
    && contextServiceSource.includes('export function selectRailEncounterId(phaseSnapshot) {')
    && contextServiceSource.includes('export function selectRailTurnEnvelope(hexmap, phaseSnapshot, selectedEntity) {')
    && contextServiceSource.includes('const serverTurnActor = hasServerTurn ? selectRailCanonicalActorByRef(hexmap, serverTurnEntity) : null;')
    && contextServiceSource.includes('const selectedControllableActor = selectedEntity')
    && contextServiceSource.includes('&& typeof hexmap?.canDragEntityOnMap === \'function\'')
    && contextServiceSource.includes('&& hexmap.canDragEntityOnMap(selectedEntity)')
    && contextServiceSource.includes('actor = serverTurnActor')
    && contextServiceSource.includes('|| selectedControllableActor')
    && contextServiceSource.includes('export function selectRailActionHydrationPending(turnEnvelope, phaseSnapshot, actionContract, availableActions) {')
    && contextServiceSource.includes('export function selectRailStatusLabel(turnEnvelope, isActorTurn, actionState, automationState, actionHydrationPending = false) {')
    && contextServiceSource.includes('const encounterScopedActorRef = (turnEnvelope.hasServerTurn && actionState.hasTurnScopedAction)')
    && contextServiceSource.includes('const actorFromRef = selectRailCanonicalActorByRef(hexmap, actorRef);')
    && contextServiceSource.includes('else if (turnEnvelope.hasServerTurn && String(turnEnvelope.serverTurnEntity || \'\').trim() === String(actorRef).trim()) {')
    && contextServiceSource.includes('actorName = String(actionState.currentTurnLabel || actorRef).trim() || String(actorRef).trim();')
    && contextServiceSource.includes('const encounterId = selectRailEncounterId(phaseSnapshot);')
    && contextServiceSource.includes('const campaignClock = phaseSnapshot?.campaignClock')
    && contextServiceSource.includes('|| phaseSnapshot?.gameTime')
    && contextServiceSource.includes('|| runtimeGameState?.campaign_clock')
    && contextServiceSource.includes('|| runtimeGameState?.game_time')
    && contextServiceSource.includes('const timedActivities = Array.isArray(phaseSnapshot?.timedActivities)')
    && contextServiceSource.includes('Array.isArray(runtimeGameState?.timed_activities) ? runtimeGameState.timed_activities : []')
    && contextServiceSource.includes('encounterId,')
    && contextServiceSource.includes('availableActions: Array.isArray(phaseSnapshot?.availableActions) ? phaseSnapshot.availableActions : [],')
    && contextServiceSource.includes('const actionHydrationPending = selectRailActionHydrationPending(')
    && contextServiceSource.includes('awaitingHydration: actionHydrationPending,')
    && contextServiceSource.includes('actionContract,')
    && contextServiceSource.includes('statusLabel,'),
  'context service falls back to canonical runtime game state for campaign clock and timed-activity data'
);

assert(
  /import \{ buildActionRailContext \} from '\.\.\/services\/action-rail-context-service\.js(\?v=[^']*)?';/.test(panelSource)
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
