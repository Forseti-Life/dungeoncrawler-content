/**
 * @file
 * Contract test: RelationshipAttitudeService must expose edge-attitude
 * resolution helpers used by combat policy input normalization.
 *
 * Run with:
 *   node tests/relationship_attitude_resolution_contract_test.js
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
  path.resolve(__dirname, '../src/Service/RelationshipAttitudeService.php'),
  'utf8',
);

console.log('\n=== Relationship attitude edge resolution contract ===');

assert(
  source.includes('public function resolveEdgeAttitude(string $source_entity_ref, array $target_entity_refs, int $campaign_id): string')
    && source.includes('@deprecated Use resolveEdgeDispositionDetails() for score-first authority.')
    && source.includes("$source_types = ['campaign_npc', 'campaign_character'];"),
  'RelationshipAttitudeService retains compatibility attitude resolver with explicit deprecation toward score-first authority'
);

assert(
  source.includes('protected function buildEntityRefCandidates(string $entity_ref): array')
    && source.includes("if (!str_starts_with($candidate, 'npc_')) {")
    && source.includes("$candidates[] = 'npc_' . $candidate;"),
  'RelationshipAttitudeService builds entity-ref candidate set with npc prefix normalization'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
