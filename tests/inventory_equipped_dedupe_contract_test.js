/**
 * @file
 * Contract test: equipped inventory should not be triple-counted.
 *
 * Run with:
 *   node tests/inventory_equipped_dedupe_contract_test.js
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

const characterPanelSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/panels/CharacterPanel.js'),
  'utf8',
);
const inventoryPanelSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/panels/InventoryPanel.js'),
  'utf8',
);

console.log('\n=== Inventory equipped dedupe contract ===');

assert(
  characterPanelSource.includes('equipped: hasExplicitEquipped ? equippedItems : [],'),
  'CharacterPanel does not mirror derived equipped items into a second inventory list'
);

assert(
  inventoryPanelSource.includes('const hasInventoryItems = (')
    && inventoryPanelSource.includes('const equipmentFallback = hasInventoryItems ? [] : (context?.equipment || []);')
    && inventoryPanelSource.includes('const items = collectInventoryItems(inventory, equipmentFallback);'),
  'InventoryPanel only uses equipment fallback when inventory surfaces are empty'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}

