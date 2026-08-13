/**
 * @file
 * Contract test: matrix and actor projection share disposition resolver authority.
 *
 * Run with:
 *   node tests/disposition_resolver_parity_contract_test.js
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

const projectionSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ActorContextProjectionService.php'),
  'utf8',
);
const matrixSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RelationshipsMatrixReadModelService.php'),
  'utf8',
);

console.log('\n=== Disposition resolver parity contract ===');

assert(
  projectionSource.includes('protected DispositionResolverService $dispositionResolverService;')
    && projectionSource.includes('buildResolvedDispositionByTarget(')
    && projectionSource.includes('dispositionResolverService->resolveDispositionMap('),
  'Actor context projection resolves per-target disposition through canonical resolver service'
);

assert(
  matrixSource.includes('protected readonly DispositionResolverService $dispositionResolverService')
    && matrixSource.includes('dispositionResolverService->resolveActorTargetDisposition(')
    && matrixSource.includes("'resolver_snapshot' => $resolver_dto"),
  'Relationships matrix read model resolves final score/label through canonical resolver snapshot'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
