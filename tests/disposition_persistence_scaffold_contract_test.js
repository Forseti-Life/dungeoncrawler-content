/**
 * @file
 * Contract test: disposition state/event persistence scaffold and integration.
 *
 * Run with:
 *   node tests/disposition_persistence_scaffold_contract_test.js
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
  path.resolve(__dirname, '../src/Service/DispositionEventStoreService.php'),
  'utf8',
);
const stateStoreSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/DispositionStateStoreService.php'),
  'utf8',
);
const dispositionSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ActorDispositionService.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Disposition persistence scaffold contract ===');

assert(
  eventStoreSource.includes('class DispositionEventStoreService')
    && eventStoreSource.includes("protected const MAX_EVENTS = 500;")
    && eventStoreSource.includes('hasDispositionEventIdempotencyKey(')
    && eventStoreSource.includes('resolveIdempotencyKey(array $event): string')
    && eventStoreSource.includes("'idempotency_key' => $idempotency_key")
    && eventStoreSource.includes("$state['disposition_events'] = $events;")
    && eventStoreSource.includes("'score_before' =>")
    && eventStoreSource.includes("'score_after' =>")
    && eventStoreSource.includes('$this->persistEventRow($campaign_id, $record);')
    && eventStoreSource.includes("tableExists('dc_disposition_events')")
    && eventStoreSource.includes("->insert('dc_disposition_events')"),
  'Disposition event store persists bounded history, supports idempotency-key lookup, and dual-writes canonical event rows'
);

assert(
  stateStoreSource.includes('class DispositionStateStoreService')
    && stateStoreSource.includes("$state['disposition_state'] = $registry;")
    && stateStoreSource.includes('normalizeSummary(array $summary): array')
    && stateStoreSource.includes("['current_score']")
    && stateStoreSource.includes("['score_source']")
    && stateStoreSource.includes("'summary' => $summary,")
    && stateStoreSource.includes('$this->persistStateRow($campaign_id, $snapshot);')
    && stateStoreSource.includes("tableExists('dc_disposition_state')")
    && stateStoreSource.includes("->merge('dc_disposition_state')"),
  'Disposition state store persists normalized numeric summary and dual-writes canonical state rows'
);

assert(
  dispositionSource.includes('DispositionEventStoreService $disposition_event_store_service')
    && dispositionSource.includes('DispositionStateStoreService $disposition_state_store_service')
    && dispositionSource.includes("'current_score' =>")
    && dispositionSource.includes("'score_source' =>")
    && dispositionSource.includes("'score_before' =>")
    && dispositionSource.includes("'score_after' =>")
    && dispositionSource.includes('hasDispositionEventIdempotencyKey($campaign_id, $resolved_ref, $trigger_idempotency_key)')
    && dispositionSource.includes("'idempotency_key' => $trigger_idempotency_key")
    && dispositionSource.includes('$this->dispositionEventStoreService->recordDispositionEvent(')
    && dispositionSource.includes('$this->dispositionStateStoreService->storeLatestState('),
  'ActorDispositionService dedupes durable trigger events by idempotency key and writes numeric score-bearing events on mutation paths'
);

assert(
  servicesSource.includes('dungeoncrawler_content.disposition_event_store_service:')
    && servicesSource.includes('dungeoncrawler_content.disposition_state_store_service:')
    && servicesSource.includes("- '@database'")
    && servicesSource.includes("- '@dungeoncrawler_content.disposition_event_store_service'")
    && servicesSource.includes("- '@dungeoncrawler_content.disposition_state_store_service'"),
  'Service wiring injects database for disposition dual-write and registers scaffold stores'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
