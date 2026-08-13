/**
 * @file
 * Contract test: runtime read model includes actor-scoped disposition state.
 *
 * Run with:
 *   node tests/runtime_read_disposition_state_projection_contract_test.js
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
  path.resolve(__dirname, '../src/Service/RuntimeStateReadModelAssembler.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Runtime read disposition-state projection contract ===');

assert(
  source.includes("'disposition_state' => $disposition_state,")
    && source.includes('protected function loadActorDispositionState(int $campaign_id, ?array $actor_entity, string $actor_id): ?array'),
  'RuntimeStateReadModelAssembler projects disposition_state via dedicated resolver'
);

assert(
  source.includes('protected ?DispositionStateStoreService $dispositionStateStoreService;')
    && source.includes("$this->dispositionStateStoreService = $disposition_state_store_service")
    && source.includes('$stored = $this->dispositionStateStoreService->loadLatestState($campaign_id, $primary_entity_ref);')
    && servicesSource.includes('dungeoncrawler_content.runtime_state_read_model_assembler:')
    && servicesSource.includes("- '@dungeoncrawler_content.disposition_state_store_service'"),
  'RuntimeStateReadModelAssembler uses canonical disposition state-store service authority for actor disposition reads'
);

assert(
  source.includes("$registry = is_array($state['disposition_state'] ?? NULL) ? $state['disposition_state'] : [];")
    && source.includes("tableExists('dc_disposition_state')")
    && source.includes("select('dc_disposition_state', 's')")
    && source.includes('protected function buildDispositionEntityRefCandidates(?array $actor_entity, string $actor_id): array')
    && source.includes("'entity_ref' => (string) ($entry['entity_ref'] ?? $candidate),"),
  'RuntimeStateReadModelAssembler resolves actor disposition state from canonical table with registry fallback using entity-ref candidates'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
