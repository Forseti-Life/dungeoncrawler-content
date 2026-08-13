/**
 * @file
 * Contract test: character state output should include canonical
 * stance summary projection when campaign/entity context exists.
 *
 * Run with:
 *   node tests/character_state_stance_summary_projection_contract_test.js
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
  path.resolve(__dirname, '../src/Service/CharacterStateService.php'),
  'utf8',
);

console.log('\n=== Character state stance summary projection contract ===');

assert(
  source.includes('$state = $this->applyStanceSummaryProjection($state);')
    && source.includes('protected function applyStanceSummaryProjection(array $state): array'),
  'CharacterStateService applies stance summary projection during state assembly'
);

assert(
  source.includes("!\\Drupal::hasService('dungeoncrawler_content.actor_context_projection_service')")
    && source.includes('$summary = $service->buildStanceSummary($state, $campaign_id, $entity_ref);')
    && source.includes("$state['stance_summary'] = $summary;"),
  'CharacterStateService projects canonical stance summary via ActorContextProjectionService'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
