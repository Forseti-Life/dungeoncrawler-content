/**
 * @file
 * Contract checks for follower actor schema enforcement.
 *
 * Run with:
 *   node tests/follower_actor_schema_contract_test.js
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
  path.resolve(__dirname, '../src/Service/FollowerSubsystemService.php'),
  'utf8'
);

console.log('\n=== Follower actor schema contract ===');

assert(
  source.includes("public const FOLLOWER_ACTOR_SCHEMA_VERSION = 'follower-actor-v2';"),
  'follower subsystem defines a canonical actor schema version'
);

assert(
  source.includes('protected function buildCanonicalFollowerActorMetadata(')
    && source.includes("'abilities' => is_array($metadata['abilities'] ?? NULL) ? $metadata['abilities'] : [],")
    && source.includes("'skills' => is_array($metadata['skills'] ?? NULL) ? $metadata['skills'] : [],")
    && source.includes("'follower_contract' => is_array($metadata['follower_contract'] ?? NULL) ? $metadata['follower_contract'] : [],"),
  'follower metadata normalization includes canonical parity domains'
);

assert(
  source.includes('protected function assertCanonicalFollowerActorRecord(string $follower_kind, array $actor_record): void {')
    && source.includes("Follower actor record \"%s\" metadata domain \"%s\" must be an array.")
    && source.includes("Follower actor record \"%s\" must include instance/content identity."),
  'follower subsystem enforces strict actor-record schema validation'
);

assert(
  source.includes('$this->assertCanonicalFollowerActorRecord($follower_kind, $actor_record);')
    && source.includes('$actor_record = [')
    && source.includes('return $actor_record;'),
  'validation is enforced in both resolve and persist paths'
);

console.log('\n======================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
