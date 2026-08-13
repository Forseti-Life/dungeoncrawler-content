/**
 * @file
 * Contract test: Arcane Cascade stance mutations must route through
 * StanceRuntimeService as the canonical stance runtime authority.
 *
 * Run with:
 *   node tests/som_arcane_cascade_stance_runtime_contract_test.js
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

console.log('\n=== SOM Arcane Cascade stance runtime contract ===');

assert(
  source.includes('use Drupal\\dungeoncrawler_content\\Service\\StanceRuntimeService;')
    && source.includes('protected StanceRuntimeService $stanceRuntimeService;'),
  'SomController declares StanceRuntimeService dependency'
);

assert(
  source.includes("$container->get('dungeoncrawler_content.stance_runtime_service')"),
  'SomController factory resolves stance runtime service from container'
);

assert(
  source.includes("$data = $this->stanceRuntimeService->enterStance($data, 'arcane_cascade'")
    && source.includes("$data = $this->stanceRuntimeService->exitStance($data, 'arcane_cascade'"),
  'Arcane Cascade enter/exit routes through StanceRuntimeService'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
