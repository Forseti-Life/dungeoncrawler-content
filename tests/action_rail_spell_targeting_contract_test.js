/**
 * @file
 * Contract test: spell action targeting wiring from rail -> encounter action.
 *
 * Run with:
 *   node tests/action_rail_spell_targeting_contract_test.js
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
const encounterSystemSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/EncounterSystem.js'), 'utf8');

console.log('\n=== Action rail spell targeting contracts ===');

assert(
  panelSource.includes('resolveSpellTargetingMode(option = {}, metadata = {}) {')
    && panelSource.includes('isSpellTargetRequired(targeting = \'\') {')
    && panelSource.includes('isTargetingModeMapPickRequired(targeting = \'\') {')
    && panelSource.includes('const targeting = this.resolveSpellTargetingMode(option, metadata);')
    && panelSource.includes('const targetRequired = this.isSpellTargetRequired(targeting);')
    && panelSource.includes('const maxTargets = Number.isFinite(explicitMaxTargets) && explicitMaxTargets > 0')
    && panelSource.includes('targeting,')
    && panelSource.includes("targetRequired: targetRequired ? '1' : '0',")
    && panelSource.includes('minTargets: String(minTargets),')
    && panelSource.includes('maxTargets: String(Math.max(minTargets, maxTargets || 1)),'),
  'spell entries include canonical targeting metadata, required-target gate wiring, and target-count contract fields'
);

assert(
  encounterSystemSource.includes("targeting: String(button.dataset.targeting || 'contextual').trim().toLowerCase(),")
    && encounterSystemSource.includes("targetRequired: button.dataset.targetRequired === '1',")
    && encounterSystemSource.includes('const targets = this._resolveButtonTargets(context, button);')
    && encounterSystemSource.includes('const targetHex = this._resolveButtonTargetHex(context, button);')
    && encounterSystemSource.includes("this._appendChatLine('System', 'That spell requires a selected target on the map.', 'system');")
    && encounterSystemSource.includes('target_hex: targetHex || undefined,')
    && encounterSystemSource.includes('targets: targets.length > 0 ? targets : undefined,')
    && encounterSystemSource.includes("}, spellTargetRef ? { target: spellTargetRef } : {});")
    && encounterSystemSource.includes("...(spellTargetRef ? { target: spellTargetRef } : {}),"),
  'direct spell execution sends explicit coordinator target refs plus canonical targets[] and map hex payloads'
);

console.log('\n============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
