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
  source.includes('protected ?SocialStateService $socialStateService;')
    && source.includes("$this->socialStateService = $social_state_service")
    && source.includes('$stored = $this->socialStateService->readActorStance($campaign_id, $candidate);')
    && servicesSource.includes('dungeoncrawler_content.runtime_state_read_model_assembler:')
    && servicesSource.includes("- '@dungeoncrawler_content.social_state'"),
  'RuntimeStateReadModelAssembler routes actor stance reads through the canonical SocialStateService authority'
);

assert(
  !source.includes("select('dc_stance_state', 's')")
    && !source.includes('$this->stanceStateStoreService')
    && source.includes("'summary' => is_array($stored['summary'] ?? NULL) ? $stored['summary'] : [],"),
  'RuntimeStateReadModelAssembler no longer reads the raw stance store/table directly; the social owner is the single authority'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
