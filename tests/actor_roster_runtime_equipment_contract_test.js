/**
 * @file
 * Contract test: NPC runtime sync must surface canonical equipment/inventory.
 *
 * Run with:
 *   node tests/actor_roster_runtime_equipment_contract_test.js
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

const runtimeSyncSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CampaignCharacterRuntimeSyncService.php'),
  'utf8',
);

console.log('\n=== Runtime NPC equipment projection contract ===');

assert(
  runtimeSyncSource.includes("$runtime_equipment = array_values(is_array($state['equipment'] ?? NULL) ? $state['equipment'] : []);")
    && runtimeSyncSource.includes("$runtime_inventory = is_array($state['inventory'] ?? NULL) ? $state['inventory'] : [];")
    && runtimeSyncSource.includes("$runtime_inventory = ['carried' => $runtime_equipment];"),
  'Runtime sync derives deterministic equipment/inventory payloads from canonical NPC state_data'
);

assert(
  runtimeSyncSource.includes("$entity['state']['equipment'] = $runtime_equipment;")
    && runtimeSyncSource.includes("$entity['state']['inventory'] = $runtime_inventory;")
    && runtimeSyncSource.includes("$entity['state']['metadata']['equipment'] = $runtime_equipment;"),
  'Matched NPC entities are hydrated with equipment/inventory in state and metadata surfaces'
);

assert(
  runtimeSyncSource.includes("'equipment' => $runtime_equipment,")
    && runtimeSyncSource.includes("'inventory' => $runtime_inventory,")
    && runtimeSyncSource.includes("'resources' => ["),
  'Newly materialized NPC entities include equipment/inventory/resources payloads'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}

