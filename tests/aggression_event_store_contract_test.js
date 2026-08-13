/**
 * @file
 * Contract test: phase-1 combat-entry/aggression persistence scaffold wiring.
 *
 * Run with:
 *   node tests/aggression_event_store_contract_test.js
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

const storeSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/AggressionEventStoreService.php'),
  'utf8',
);
const combatEntrySource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CombatEntryService.php'),
  'utf8',
);

console.log('\n=== Aggression event store scaffold contract ===');

assert(
  storeSource.includes('class AggressionEventStoreService')
    && storeSource.includes("protected const MAX_EVENTS = 250;")
    && storeSource.includes("$state['combat_entry_events'] = $events;")
    && storeSource.includes('$this->persistEventRow($campaign_id, $record);'),
  'Aggression event store persists bounded combat-entry event history on campaign state and forwards to canonical row store'
);

assert(
  storeSource.includes("tableExists('dc_aggression_events')")
    && storeSource.includes("->insert('dc_aggression_events')"),
  'Aggression event store dual-writes to dc_aggression_events when canonical table exists'
);

assert(
  combatEntrySource.includes('AggressionEventStoreService $aggression_event_store_service')
    && combatEntrySource.includes('$this->aggressionEventStoreService->recordCombatEntryEvent($campaign_id, [')
    && combatEntrySource.includes("'status' => 'entered'"),
  'CombatEntryService records combat-entry decision events through persistence scaffold'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
