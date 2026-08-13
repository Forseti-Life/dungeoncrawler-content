/**
 * @file
 * Contract test: hazard action-log fallback consumes hazard resolution envelope packets first.
 *
 * Run with:
 *   node tests/combat_projection_hazard_envelope_contract_test.js
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

function read(relPath) {
  return fs.readFileSync(path.resolve(__dirname, relPath), 'utf8');
}

console.log('\n=== Combat projection hazard envelope contract ===');

const chatPanelSource = read('../js/v2/panels/ChatPanel.js');
assert(
  chatPanelSource.includes('const hazardResolutionEnvelope = (data?.hazard_resolution_envelope')
    && chatPanelSource.includes("const hazardDamagePacket = findHazardResolutionPacket('damage_application')")
    && chatPanelSource.includes("case 'hazard_triggered':")
    && chatPanelSource.includes('hazardDamagePacket?.amount')
    && chatPanelSource.includes('hazardDamagePacket?.damage_type'),
  'Hazard fallback projection consumes hazard resolution-envelope packets before legacy hazard-only fields'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
