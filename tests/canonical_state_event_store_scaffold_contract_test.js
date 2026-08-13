/**
 * @file
 * Contract test: canonical persistence scaffolds cover disposition, aggression,
 * and stance state/event stores with table-backed persistence guards.
 *
 * Run with:
 *   node tests/canonical_state_event_store_scaffold_contract_test.js
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

const dispositionState = fs.readFileSync(path.resolve(__dirname, '../src/Service/DispositionStateStoreService.php'), 'utf8');
const dispositionEvents = fs.readFileSync(path.resolve(__dirname, '../src/Service/DispositionEventStoreService.php'), 'utf8');
const aggressionState = fs.readFileSync(path.resolve(__dirname, '../src/Service/AggressionStateStoreService.php'), 'utf8');
const aggressionEvents = fs.readFileSync(path.resolve(__dirname, '../src/Service/AggressionEventStoreService.php'), 'utf8');
const stanceState = fs.readFileSync(path.resolve(__dirname, '../src/Service/StanceStateStoreService.php'), 'utf8');
const stanceEvents = fs.readFileSync(path.resolve(__dirname, '../src/Service/StanceEventStoreService.php'), 'utf8');

console.log('\n=== Canonical state/event store scaffold contract ===');

assert(
  dispositionState.includes("tableExists('dc_disposition_state')")
    && dispositionEvents.includes("tableExists('dc_disposition_events')"),
  'Disposition state and event stores persist through canonical table scaffolds'
);

assert(
  aggressionState.includes("tableExists('dc_aggression_state')")
    && aggressionEvents.includes("tableExists('dc_aggression_events')"),
  'Aggression state and event stores persist through canonical table scaffolds'
);

assert(
  stanceState.includes("tableExists('dc_stance_state')")
    && stanceEvents.includes("tableExists('dc_stance_events')"),
  'Stance state and event stores persist through canonical table scaffolds'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
