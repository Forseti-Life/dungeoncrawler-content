/**
 * @file
 * Contract test: NPC equipment-only state_data can be materialized into
 * inventory item instances for runtime editing.
 *
 * Run with:
 *   node tests/npc_inventory_materialization_contract_test.js
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

const source = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/InventoryManagementService.php'),
  'utf8',
);

console.log('\n=== NPC inventory materialization contract ===');

assert(
  source.includes("if ($entries === []) {")
    && source.includes("$equipment_source = [];")
    && source.includes("is_array($state['equipment'] ?? NULL)")
    && source.includes("is_array($state['npcDefinition']['equipment'] ?? NULL)"),
  'Materialization falls back to equipment sources when inventory arrays are empty'
);

assert(
  source.includes("default => (!empty($equipment_item['equipped']) || !empty($equipment_item['worn'])) ? 'worn' : 'carried',"),
  'Equipped NPC equipment is mapped into worn inventory location during materialization'
);

assert(
  source.includes("$item_name = trim((string) ($item['name'] ?? ''));")
    && source.includes("$item_id = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $item_name));"),
  'Missing item IDs are synthesized from item names so instance rows can be created'
);

assert(
  source.includes("'bootstrap_%d_%s_%d'")
    && source.includes('(int) $character_id'),
  'Bootstrap item_instance IDs are character-scoped to avoid cross-actor collisions'
);

assert(
  source.includes("->merge('dc_campaign_item_instances')")
    && source.includes("'campaign_id' => $campaign_id")
    && source.includes("'item_instance_id' => $item_instance_id"),
  'Bootstrap materialization uses campaign+instance upsert keys to stay idempotent under concurrent startup reads'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
