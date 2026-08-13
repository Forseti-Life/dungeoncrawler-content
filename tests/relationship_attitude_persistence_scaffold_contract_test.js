/**
 * @file
 * Contract test: relationship-attitude state/event persistence scaffolds.
 *
 * Run with:
 *   node tests/relationship_attitude_persistence_scaffold_contract_test.js
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
  path.resolve(__dirname, '../src/Service/RelationshipAttitudeEventStoreService.php'),
  'utf8',
);
const stateStoreSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RelationshipAttitudeStateStoreService.php'),
  'utf8',
);
const serviceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RelationshipAttitudeService.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Relationship attitude persistence scaffold contract ===');

assert(
  eventStoreSource.includes('class RelationshipAttitudeEventStoreService')
    && eventStoreSource.includes("protected const MAX_EVENTS = 500;")
    && eventStoreSource.includes('hasRelationshipAttitudeEventIdempotencyKey(')
    && eventStoreSource.includes('resolveIdempotencyKey(array $event): string')
    && eventStoreSource.includes("'idempotency_key' => $idempotency_key")
    && eventStoreSource.includes("$state['relationship_attitude_events'] = $events;")
    && eventStoreSource.includes("'score_before' =>")
    && eventStoreSource.includes("'score_after' =>")
    && eventStoreSource.includes('$this->persistEventRow($campaign_id, $record);')
    && eventStoreSource.includes("tableExists('dc_relationship_attitude_events')")
    && eventStoreSource.includes("->insert('dc_relationship_attitude_events')"),
  'Relationship attitude event store persists bounded history, supports idempotency-key lookup, and dual-writes canonical event rows'
);

assert(
  stateStoreSource.includes('class RelationshipAttitudeStateStoreService')
    && stateStoreSource.includes("$state['relationship_attitude_state'] = $registry;")
    && stateStoreSource.includes("'score' => $resolved_score")
    && stateStoreSource.includes("'score_source' => $score_source")
    && stateStoreSource.includes('public function findStrongestAttitude(int $campaign_id, array $source_candidates, array $target_candidates): string')
    && stateStoreSource.includes('public function findStrongestDisposition(int $campaign_id, array $source_candidates, array $target_candidates): ?array')
    && stateStoreSource.includes('$this->persistStateRow($campaign_id, $key, $snapshot);')
    && stateStoreSource.includes("tableExists('dc_relationship_attitude_state')")
    && stateStoreSource.includes("->merge('dc_relationship_attitude_state')"),
  'Relationship attitude state store persists numeric edge state, dual-writes canonical state rows, and exposes candidate-based lookup'
);

assert(
  serviceSource.includes('RelationshipAttitudeEventStoreService $event_store_service')
    && serviceSource.includes('RelationshipAttitudeStateStoreService $state_store_service')
    && serviceSource.includes("'score' => $resolved_score")
    && serviceSource.includes("'score_source' => $score_source")
    && serviceSource.includes("'score_before' =>")
    && serviceSource.includes("'score_after' =>")
    && serviceSource.includes('hasRelationshipAttitudeEventIdempotencyKey(')
    && serviceSource.includes("'idempotency_key' => $idempotency_key")
    && serviceSource.includes('$this->stateStoreService->storeLatestState(')
    && serviceSource.includes('$this->eventStoreService->recordAttitudeEvent(')
    && serviceSource.includes('$stored = $this->stateStoreService->findStrongestAttitude('),
  'RelationshipAttitudeService writes numeric persistence scaffolds, enforces relationship idempotency, and consults stored state during edge attitude resolution'
);

assert(
  servicesSource.includes('dungeoncrawler_content.relationship_attitude_event_store_service:')
    && servicesSource.includes('dungeoncrawler_content.relationship_attitude_state_store_service:')
    && servicesSource.includes("- '@database'")
    && servicesSource.includes("- '@dungeoncrawler_content.relationship_attitude_event_store_service'")
    && servicesSource.includes("- '@dungeoncrawler_content.relationship_attitude_state_store_service'"),
  'Service wiring injects database for relationship-attitude dual-write and registers scaffold stores'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
