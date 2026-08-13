/**
 * @file
 * Contract test: runtime read model includes active-room aggression state.
 *
 * Run with:
 *   node tests/runtime_read_aggression_state_projection_contract_test.js
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

const serviceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RuntimeStateReadModelAssembler.php'),
  'utf8',
);
const servicesYml = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Runtime read aggression-state projection contract ===');

assert(
  serviceSource.includes('protected CampaignStateService $campaignStateService;')
    && serviceSource.includes('protected ?AggressionStateStoreService $aggressionStateStoreService;')
    && serviceSource.includes('protected ?Connection $database;')
    && serviceSource.includes('$this->aggressionStateStoreService = $aggression_state_store_service')
    && serviceSource.includes('$stored = $this->aggressionStateStoreService->loadLatestState($campaign_id, $active_room_id);')
    && serviceSource.includes("'aggression_state' => $aggression_state,"),
  'RuntimeStateReadModelAssembler injects canonical aggression-state authority and projects aggression_state'
);

assert(
  serviceSource.includes('protected function loadActiveRoomAggressionState(int $campaign_id, string $active_room_id): ?array')
    && serviceSource.includes("tableExists('dc_aggression_state')")
    && serviceSource.includes("select('dc_aggression_state', 's')")
    && serviceSource.includes("$registry = is_array($state['aggression_state'] ?? NULL) ? $state['aggression_state'] : [];"),
  'RuntimeStateReadModelAssembler resolves active-room aggression state from canonical table with campaign-state fallback'
);

assert(
  servicesYml.includes('dungeoncrawler_content.runtime_state_read_model_assembler:')
    && servicesYml.includes("- '@dungeoncrawler_content.campaign_state_service'")
    && servicesYml.includes("- '@database'")
    && servicesYml.includes("- '@dungeoncrawler_content.aggression_state_store_service'"),
  'Service wiring provides campaign_state/database and aggression state-store service to runtime read-model assembler'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
