/**
 * @file
 * Contract test: exploration/downtime mutation-envelope completeness.
 *
 * Run with:
 *   node tests/phase_handlers_mutation_envelope_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const explorationSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ExplorationPhaseHandler.php'),
  'utf8'
);
const downtimeSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/DowntimePhaseHandler.php'),
  'utf8'
);

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

console.log('\n=== Exploration/Downtime mutation-envelope contract ===');

assert(
  explorationSource.includes('$pre_slice_fingerprints = $this->computeRuntimeSliceFingerprints($dungeon_data);')
  && explorationSource.includes('$mutations = $this->normalizeMutationDescriptors(')
  && explorationSource.includes('$mutation_envelope = $this->ensureMutationEnvelopeIncludesChangedSlices('),
  'exploration processIntent normalizes descriptor targets and enforces changed-slice envelope completion'
);
assert(
  explorationSource.includes('protected function ensureMutationEnvelopeIncludesChangedSlices(')
  && explorationSource.includes('protected function computeRuntimeSliceFingerprints(array $dungeon_data): array')
  && explorationSource.includes('protected function normalizeMutationDescriptors(array $mutations, ?string $default_actor_id, string $default_room_id): array')
  && explorationSource.includes('$targets = $this->extractMutationEnvelopeTargets($mutations);')
  && explorationSource.includes('actor_entities changed without entity mutation targets.')
  && explorationSource.includes('rooms changed without room mutation targets.')
  && explorationSource.includes('connections changed without connection/room mutation targets.'),
  'exploration enforces descriptor-targeted changed-slice completion'
);
assert(
  downtimeSource.includes('$pre_slice_fingerprints = $this->computeRuntimeSliceFingerprints($dungeon_data);')
  && downtimeSource.includes('$mutations = $this->normalizeMutationDescriptors(')
  && downtimeSource.includes('$mutation_envelope = $this->ensureMutationEnvelopeIncludesChangedSlices('),
  'downtime processIntent normalizes descriptor targets and enforces changed-slice envelope completion'
);
assert(
  downtimeSource.includes('protected function ensureMutationEnvelopeIncludesChangedSlices(')
  && downtimeSource.includes('protected function computeRuntimeSliceFingerprints(array $dungeon_data): array')
  && downtimeSource.includes('protected function normalizeMutationDescriptors(array $mutations, ?string $default_actor_id, string $default_room_id): array')
  && downtimeSource.includes('$targets = $this->extractMutationEnvelopeTargets($mutations);')
  && downtimeSource.includes('actor_entities changed without entity mutation targets.')
  && downtimeSource.includes('rooms changed without room mutation targets.')
  && downtimeSource.includes('connections changed without connection/room mutation targets.'),
  'downtime enforces descriptor-targeted changed-slice completion'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
