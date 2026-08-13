/**
 * @file
 * Contract test: stance state/event persistence scaffold integration.
 *
 * Run with:
 *   node tests/stance_persistence_scaffold_contract_test.js
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

const eventStoreSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/StanceEventStoreService.php'),
  'utf8',
);
const stateStoreSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/StanceStateStoreService.php'),
  'utf8',
);
const runtimeSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/StanceRuntimeService.php'),
  'utf8',
);
const controllerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/SomController.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Stance persistence scaffold contract ===');

assert(
  eventStoreSource.includes('class StanceEventStoreService')
    && eventStoreSource.includes("protected const MAX_EVENTS = 500;")
    && eventStoreSource.includes("$state['stance_events'] = $events;")
    && eventStoreSource.includes('$this->persistEventRow($campaign_id, $record);')
    && eventStoreSource.includes("tableExists('dc_stance_events')")
    && eventStoreSource.includes("->insert('dc_stance_events')"),
  'Stance event store persists bounded transition history and dual-writes canonical event rows'
);

assert(
  stateStoreSource.includes('class StanceStateStoreService')
    && stateStoreSource.includes("$state['stance_state'] = $registry;")
    && stateStoreSource.includes("'summary' => $summary,")
    && stateStoreSource.includes('$this->persistStateRow($campaign_id, $snapshot);')
    && stateStoreSource.includes("tableExists('dc_stance_state')")
    && stateStoreSource.includes("->merge('dc_stance_state')"),
  'Stance state store persists latest per-actor summary and dual-writes canonical state rows'
);

assert(
  runtimeSource.includes('StanceEventStoreService $stance_event_store_service')
    && runtimeSource.includes('StanceStateStoreService $stance_state_store_service')
    && runtimeSource.includes('$this->persistStanceProjection(')
    && runtimeSource.includes('$this->stanceStateStoreService->storeLatestState(')
    && runtimeSource.includes('$this->stanceEventStoreService->recordStanceEvent('),
  'StanceRuntimeService records stance events and latest state on enter/exit transitions'
);

assert(
  controllerSource.includes('$campaign_id = (int) ($record->campaign_id ?? 0);')
    && controllerSource.includes('$entity_ref = trim((string) ($record->instance_id ?? $character_id));')
    && controllerSource.includes("$this->stanceRuntimeService->enterStance($data, 'arcane_cascade', 1, [], $campaign_id, $entity_ref);")
    && controllerSource.includes("$this->stanceRuntimeService->exitStance($data, 'arcane_cascade', [], $campaign_id, $entity_ref);"),
  'SomController passes campaign/entity context to stance runtime transitions'
);

assert(
  servicesSource.includes('dungeoncrawler_content.stance_event_store_service:')
    && servicesSource.includes('dungeoncrawler_content.stance_state_store_service:')
    && servicesSource.includes("- '@database'")
    && servicesSource.includes("- '@dungeoncrawler_content.stance_event_store_service'")
    && servicesSource.includes("- '@dungeoncrawler_content.stance_state_store_service'"),
  'Service wiring injects database for stance dual-write and registers stance runtime dependencies'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
