/**
 * @file
 * Contract test: ActorDispositionService must normalize prefixed entity refs
 * before psychology profile lookup.
 *
 * Run with:
 *   node tests/actor_disposition_entity_ref_resolution_contract_test.js
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
  path.resolve(__dirname, '../src/Service/ActorDispositionService.php'),
  'utf8',
);

console.log('\n=== Actor disposition entity-ref resolution contract ===');

assert(
  source.includes('protected function resolveEntityRef(int $campaign_id, string $entity_ref): string')
    && source.includes('strpos($entity_ref, \':\')')
    && source.includes("$candidates[] = substr($entity_ref, $colon_pos + 1);"),
  'ActorDispositionService extracts canonical candidate after prefixed entity refs'
);

assert(
  source.includes('if (!str_starts_with($candidate, \'npc_\')) {')
    && source.includes("$candidates[] = 'npc_' . $candidate;")
    && source.includes('if (is_array($this->npcPsychologyService->loadProfile($campaign_id, $candidate))) {'),
  'ActorDispositionService normalizes npc prefixes and verifies profile-backed candidate'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}

