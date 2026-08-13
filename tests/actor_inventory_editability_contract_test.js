/**
 * @file
 * Contract test: runtime actor payload must carry campaign character identity
 * so inventory endpoints can mutate actor inventory in campaign/sandbox mode.
 *
 * Run with:
 *   node tests/actor_inventory_editability_contract_test.js
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

const panelSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/panels/CharacterPanel.js'),
  'utf8',
);

console.log('\n=== Runtime actor inventory editability contract ===');

assert(
  panelSource.includes('const runtimeCharacterId = Number(')
    && panelSource.includes('metadata.character_id')
    && panelSource.includes('metadata.campaign_character_id'),
  'CharacterPanel derives runtime actor character identity from canonical metadata'
);

assert(
  panelSource.includes('id: runtimeCharacterId || null,')
    && panelSource.includes('character_id: runtimeCharacterId || null,')
    && panelSource.includes('characterId: runtimeCharacterId || null,'),
  'Runtime actor launch payload exposes character identifiers for inventory actions'
);

assert(
  panelSource.includes("this.bus.emit('character:inventory-refresh-requested', this.currentCharacterInventoryContext);"),
  'CharacterPanel requests canonical inventory refresh after actor sheet hydration'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
