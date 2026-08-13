/**
 * @file
 * Contract test: shove fallback projection prefers movement/damage packets from envelopes.
 *
 * Run with:
 *   node tests/combat_projection_shove_envelope_contract_test.js
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

console.log('\n=== Combat projection shove envelope contract ===');

const chatPanelSource = read('../js/v2/panels/ChatPanel.js');
assert(
  chatPanelSource.includes("case 'shove':")
    && chatPanelSource.includes('const forcedToHex = movementPacket?.to_hex')
    && chatPanelSource.includes('const hazardDamage = Number(damagePacket?.amount);')
    && chatPanelSource.includes('damagePacket?.damage_type'),
  'Shove fallback projection consumes envelope-derived movement/damage packets before legacy shove-only fields'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
