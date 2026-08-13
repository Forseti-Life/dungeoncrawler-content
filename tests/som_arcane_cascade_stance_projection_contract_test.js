/**
 * @file
 * Contract test: Arcane Cascade response exposes stance projection summary.
 *
 * Run with:
 *   node tests/som_arcane_cascade_stance_projection_contract_test.js
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
  path.resolve(__dirname, '../src/Controller/SomController.php'),
  'utf8',
);

console.log('\n=== SOM Arcane Cascade stance projection contract ===');

assert(
  source.includes('ActorContextProjectionService $actor_context_projection_service')
    && source.includes("$container->get('dungeoncrawler_content.actor_context_projection_service')"),
  'SomController injects actor context projection service'
);

assert(
  source.includes('$stance_summary = $this->actorContextProjectionService->buildStanceSummary($data, $campaign_id, $entity_ref);')
    && source.includes("'arcane_cascade_active'  => !empty($stance_summary['arcane_cascade_active']),")
    && source.includes("'stance_summary'         => $stance_summary,"),
  'Arcane Cascade response includes canonical stance summary projection'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
