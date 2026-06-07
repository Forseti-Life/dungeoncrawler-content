/**
 * @file
 * Focused regressions for Action Rail execution ownership boundaries.
 *
 * Run with:
 *   node tests/action_rail_execution_ownership_test.js
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

const encounterSystemSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/EncounterSystem.js'), 'utf8');
const playerAutomationSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/PlayerAutomation.js'), 'utf8');
const actionRailContractSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/contracts/action-rail-contract.js'), 'utf8');

console.log('\n=== Action rail execution ownership ===');

assert(
  actionRailContractSource.includes('export const ACTION_SELECTION_HANDLERS = Object.freeze({')
    && actionRailContractSource.includes("skill: 'executeDirectSkill'")
    && actionRailContractSource.includes("feat: 'executeDirectFeat'")
    && actionRailContractSource.includes("consumable: 'executeDirectConsumable'")
    && encounterSystemSource.includes("import { ACTION_SELECTION_HANDLERS, isRestActivityActionKey } from '../contracts/action-rail-contract.js';")
    && encounterSystemSource.includes('const handlerName = ACTION_SELECTION_HANDLERS[key] ||')
    && encounterSystemSource.includes('this[handlerName](d?.button);'),
  'EncounterSystem owns direct action execution dispatch through shared contract mapping'
);

assert(
  playerAutomationSource.includes('Action rail direct executions are owned by EncounterSystem.')
    && !playerAutomationSource.includes('async executeDirectFeat(button)')
    && !playerAutomationSource.includes('async executeDirectConsumable(button)'),
  'PlayerAutomation no longer duplicates feat/consumable direct execution paths'
);

assert(
  encounterSystemSource.includes("this._appendChatLine('System', 'Skill actions require an active character.', 'system');")
    && encounterSystemSource.includes("this._appendChatLine('System', 'Skill action is missing a canonical skill name.', 'system');")
    && encounterSystemSource.includes('const characterId = Number(context.characterId || 0) || 0;')
    && encounterSystemSource.includes('const skillName = String(button.dataset.skillName || \'\').replace(/_/g, \' \').trim();'),
  'skill execution enforces canonical character and skill-name guards before dispatch'
);

console.log('\n=======================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
