/**
 * @file
 * Focused regressions for encounter action-availability gating on Action Rail tabs.
 *
 * Run with:
 *   node tests/action_rail_availability_gating_test.js
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

console.log('\n=== Action rail availability gating ===');

assert(
  actionRailPanelSource.includes("const spellActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, 'cast_spell');")
    && actionRailPanelSource.includes('disabled: this.isActionRailExecutionDisabled(actionCost, context, disabled || !spellActionAvailable),'),
  'spells are disabled in encounter mode when cast_spell is unavailable in the server action contract'
);

assert(
  actionRailPanelSource.includes("const consumeActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, 'consume_item');")
    && actionRailPanelSource.includes('disabled: this.isActionRailExecutionDisabled(actionCost, context, !consumeActionAvailable),'),
  'consumables are disabled in encounter mode when consume_item is unavailable in the server action contract'
);

assert(
  actionRailPanelSource.includes("const skillActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, 'skill');")
    && actionRailPanelSource.includes('disabled: this.isActionRailExecutionDisabled(1, context, !skillActionAvailable),'),
  'skills are disabled in encounter mode when skill is unavailable in the server action contract'
);

assert(
  actionRailPanelSource.includes("const featActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, 'feat');")
    && actionRailPanelSource.includes('disabled: this.isActionRailExecutionDisabled(entry.dataset.actionCost, context, !featActionAvailable),'),
  'feats are disabled in encounter mode when feat is unavailable in the server action contract'
);

console.log('\n=======================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
