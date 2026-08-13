/**
 * @file
 * Focused regressions for Action Rail skill wiring.
 *
 * Run with:
 *   node tests/action_rail_skill_binding_test.js
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

console.log('\n=== Action rail Skill bindings ===');

assert(
  actionRailPanelSource.includes("execute: 'skill'")
    && actionRailPanelSource.includes("const actionType = String(button.dataset.actionRailExecute || '').trim();")
    && actionRailPanelSource.includes('if (isActionRailSelectableAction(actionType)) {')
    && actionRailPanelSource.includes("this.bus.emit('user:action-selected', { actionKey: actionType, button });")
    && actionRailContractSource.includes("'skill',"),
  'action rail emits skill entries through the canonical action-selected bus contract'
);

assert(
  actionRailContractSource.includes("skill: 'executeDirectSkill'")
    && encounterSystemSource.includes("import { ACTION_SELECTION_HANDLERS, isRestActivityActionKey } from '../contracts/action-rail-contract.js';")
    && encounterSystemSource.includes('const handlerName = ACTION_SELECTION_HANDLERS[key] ||')
    && encounterSystemSource.includes('this[handlerName](d?.button);')
    && encounterSystemSource.includes('async executeDirectSkill(button) {')
    && encounterSystemSource.includes("_sendCoordinatorActionWithResync(coordinator, 'skill', context.actorRef, {")
    && encounterSystemSource.includes('targeting_mode: targetingMode,')
    && encounterSystemSource.includes('target_hex: targetHex || undefined,')
    && encounterSystemSource.includes('target_room_id: targetRoomId || undefined,')
    && encounterSystemSource.includes('targets: targets.length > 0 ? targets : undefined,'),
  'encounter system handles skill action selections and dispatches canonical target payloads'
);

assert(
  encounterSystemSource.includes("actionType: 'skill'")
    && encounterSystemSource.includes('context.hexmap?.loadCharacterFromApi(characterId);'),
  'skill handler preserves non-encounter fallback parity and refreshes character state'
);

assert(
  encounterPhaseSource.includes("'skill',")
    && encounterPhaseSource.includes('protected function routeSkillIntentExecution(')
    && encounterPhaseSource.includes("$action_cost = $this->getActionCost('skill', $params);")
    && encounterPhaseSource.includes("GameEventLogger::buildEvent('skill', 'encounter', $actor_id, ["),
  'server encounter phase accepts skill intents and returns authoritative skill summaries/events'
);

console.log('\n====================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
