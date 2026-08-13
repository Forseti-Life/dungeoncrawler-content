/**
 * @file
 * Contract test: canonical aggression-state persistence scaffold wiring.
 *
 * Run with:
 *   node tests/aggression_state_store_contract_test.js
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

const stateStoreSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/AggressionStateStoreService.php'),
  'utf8',
);
const combatEntrySource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CombatEntryService.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Aggression state store scaffold contract ===');

assert(
  stateStoreSource.includes('class AggressionStateStoreService')
    && stateStoreSource.includes("$state['aggression_state'] = $registry;")
    && stateStoreSource.includes("'aggression_summary' => $aggression_summary,")
    && stateStoreSource.includes("'combat_entry_summary' => $combat_entry_summary,")
    && stateStoreSource.includes('$this->persistStateRow($campaign_id, $snapshot);'),
  'Aggression state store persists latest aggression/combat-entry summaries per room context and forwards to canonical row store'
);

assert(
  stateStoreSource.includes("tableExists('dc_aggression_state')")
    && stateStoreSource.includes("->merge('dc_aggression_state')")
    && stateStoreSource.includes('public function loadLatestState(int $campaign_id, string $room_id): ?array')
    && stateStoreSource.includes("->condition('room_id', $room_key)")
    && stateStoreSource.includes("$entry = $registry[$room_key] ?? NULL;"),
  'Aggression state store dual-writes to and reads from canonical per-room state (table first, campaign-state fallback)'
);

assert(
  combatEntrySource.includes('AggressionStateStoreService $aggression_state_store_service')
    && combatEntrySource.includes('$this->aggressionStateStoreService->storeLatestState(')
    && combatEntrySource.includes("'entered'"),
  'CombatEntryService updates canonical aggression state store for combat-entry outcomes'
);

assert(
  servicesSource.includes('dungeoncrawler_content.aggression_event_store_service:')
    && servicesSource.includes('dungeoncrawler_content.aggression_state_store_service:')
    && servicesSource.includes("- '@database'"),
  'Service wiring injects database connection for canonical aggression dual-write'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
