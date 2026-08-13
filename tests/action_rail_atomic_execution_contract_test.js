/**
 * @file
 * Contract checks for atomic encounter action execution hooks.
 *
 * Run with:
 *   node tests/action_rail_atomic_execution_contract_test.js
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
const encounterSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/EncounterSystem.js'), 'utf8');
const contractSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/contracts/action-rail-contract.js'), 'utf8');

console.log('\n=== Action rail atomic execution contract ===');

assert(
  contractSource.includes("stride: 'executeDirectStride'")
    && contractSource.includes("step: 'executeDirectStep'")
    && contractSource.includes("demoralize: 'executeDirectDemoralize'")
    && contractSource.includes("raise_shield: 'executeDirectRaiseShield'")
    && contractSource.includes("delay: 'executeDirectDelay'")
    && contractSource.includes("talk: 'executeDirectTalk'"),
  'shared contract maps atomic action ids to explicit EncounterSystem handler methods'
);

assert(
  panelSource.includes('const allowed = new Set([')
    && panelSource.includes("'strike'")
    && panelSource.includes("'raise_shield'")
    && panelSource.includes("'interact'")
    && panelSource.includes("'demoralize'")
    && panelSource.includes("if (normalized === 'stride')")
    && panelSource.includes("if (normalized === 'step')"),
  'ActionRailPanel exposes an explicit turn-tab combat allow-list while retaining movement handler mappings'
);

assert(
  panelSource.includes("if (executeKey === 'raise_shield') {")
    && panelSource.includes("if (executeKey === 'interact' && selectedTarget?.hasHex)")
    && panelSource.includes('} else if (targetRequired) {')
    && panelSource.includes("if (normalized === 'demoralize')")
    && panelSource.includes("'feint'")
    && panelSource.includes("'point_out'")
    && panelSource.includes("if (normalized === 'talk')"),
  'ActionRailPanel maps targeted/support atomic actions into explicit execute-key handlers'
);

assert(
  encounterSource.includes('async executeDirectMovementAction(actionType, button) {')
    && encounterSource.includes("target_hex: {")
    && encounterSource.includes("this.stateManager?.get?.('selectedHex')"),
  'EncounterSystem movement hooks support selected-hex fallback and canonical target_hex payloads'
);

assert(
  encounterSource.includes('async executeDirectDemoralize(button) {')
    && encounterSource.includes("await this.executeDirectAtomicAction('raise_shield', button);")
    && encounterSource.includes("await this.executeDirectAtomicAction('delay', button);")
    && encounterSource.includes('async executeDirectTalk(button) {')
    && encounterSource.includes('const resolvedTargetRef = this._resolveButtonTargetRef(context, button);')
    && encounterSource.includes('const targets = this._resolveButtonTargets(context, button);'),
  'EncounterSystem exposes dedicated atomic execution methods and canonical target resolver usage for demoralize/raise_shield/delay/talk/attack lanes'
);

console.log('\n=============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
