/**
 * @file
 * Contract test: stance state store provides canonical read path.
 *
 * Run with:
 *   node tests/stance_state_store_read_contract_test.js
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
  path.resolve(__dirname, '../src/Service/StanceStateStoreService.php'),
  'utf8',
);
const projectionSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ActorContextProjectionService.php'),
  'utf8',
);

console.log('\n=== Stance state store read contract ===');

assert(
  source.includes('public function loadLatestState(int $campaign_id, string $entity_ref): ?array')
    && source.includes("tableExists('dc_stance_state')")
    && source.includes("select('dc_stance_state', 's')")
    && source.includes("$registry = is_array($state['stance_state'] ?? NULL) ? $state['stance_state'] : [];"),
  'StanceStateStoreService resolves latest stance state from canonical table with campaign-state fallback'
);

assert(
  projectionSource.includes('$stored = ($campaign_id > 0 && trim($entity_ref) !== \'\')')
    && projectionSource.includes('? $this->stanceStateStoreService->loadLatestState($campaign_id, $entity_ref)')
    && projectionSource.includes("'stance' => $this->buildStanceSummary($character_data, $campaign_id, $entity_ref)"),
  'Actor context stance projection prefers canonical stance-state store before legacy payload fallback'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
