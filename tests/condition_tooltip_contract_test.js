/**
 * @file
 * Guards condition tooltip rendering in character panels.
 *
 * Run with:
 *   node tests/condition_tooltip_contract_test.js
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

const v2Source = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/CharacterPanel.js'), 'utf8');
const legacySource = fs.readFileSync(path.resolve(__dirname, '../js/hexmap.js'), 'utf8');

console.log('\n=== Condition tooltip contract ===');

assert(
  v2Source.includes('buildConditionTooltipModel(condition, projectedTooltips = {}) {')
    && v2Source.includes('if (projected && typeof projected === \'object\')')
    && v2Source.includes('state?.effectiveState?.sources?.condition_tooltips')
    && v2Source.includes('data-tooltip-enabled="true"')
    && v2Source.includes('.map((condition) => this.renderConditionTooltipEntry(condition, conditionTooltips))'),
  'v2 character panel renders conditions as tooltip-enabled entries and uses projected condition tooltip metadata'
);

assert(
  legacySource.includes('function buildConditionTooltipModel(condition, projectedTooltips = {})')
    && legacySource.includes('if (projected && typeof projected === \'object\')')
    && legacySource.includes('state?.effectiveState?.sources?.condition_tooltips')
    && legacySource.includes('data-tooltip-enabled="true"')
    && legacySource.includes('.map((condition) => renderConditionTooltipEntry(condition, conditionTooltips))'),
  'legacy character sheet renders conditions as tooltip-enabled entries and uses projected condition tooltip metadata'
);

console.log('\n=================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
