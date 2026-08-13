/**
 * @file
 * Contract test: runtime read model includes actor-scoped stance state.
 *
 * Run with:
 *   node tests/runtime_read_stance_state_projection_contract_test.js
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

console.log('\n=== Runtime read stance-state projection contract ===');

assert(
  source.includes("'stance_state' => $stance_state,")
    && source.includes('protected function loadActorStanceState(int $campaign_id, ?array $actor_entity, string $actor_id): ?array'),
  'RuntimeStateReadModelAssembler projects stance_state via dedicated resolver'
);

assert(
  source.includes('protected ?StanceStateStoreService $stanceStateStoreService;')
    && source.includes("$this->stanceStateStoreService = $stance_state_store_service")
    && source.includes('$stored = $this->stanceStateStoreService->loadLatestState($campaign_id, $primary_entity_ref);')
    && servicesSource.includes('dungeoncrawler_content.runtime_state_read_model_assembler:')
    && servicesSource.includes("- '@dungeoncrawler_content.stance_state_store_service'"),
  'RuntimeStateReadModelAssembler uses canonical stance state-store service authority for actor stance reads'
);

assert(
  source.includes("$registry = is_array($state['stance_state'] ?? NULL) ? $state['stance_state'] : [];")
    && source.includes("tableExists('dc_stance_state')")
    && source.includes("select('dc_stance_state', 's')")
    && source.includes("'summary' => is_array($entry['summary'] ?? NULL) ? $entry['summary'] : [],"),
  'RuntimeStateReadModelAssembler resolves actor stance state from canonical table with registry fallback'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
