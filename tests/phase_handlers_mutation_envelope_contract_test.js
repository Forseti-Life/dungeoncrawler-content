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
  && explorationSource.includes('$mutation_envelope = $this->ensureMutationEnvelopeIncludesChangedSlices('),
  'exploration processIntent enforces changed-slice mutation envelope completion'
);
assert(
  explorationSource.includes('protected function ensureMutationEnvelopeIncludesChangedSlices(')
  && explorationSource.includes('protected function computeRuntimeSliceFingerprints(array $dungeon_data): array'),
  'exploration defines runtime-slice completeness helpers'
);
assert(
  downtimeSource.includes('$pre_slice_fingerprints = $this->computeRuntimeSliceFingerprints($dungeon_data);')
  && downtimeSource.includes('$mutation_envelope = $this->ensureMutationEnvelopeIncludesChangedSlices('),
  'downtime processIntent enforces changed-slice mutation envelope completion'
);
assert(
  downtimeSource.includes('protected function ensureMutationEnvelopeIncludesChangedSlices(')
  && downtimeSource.includes('protected function computeRuntimeSliceFingerprints(array $dungeon_data): array'),
  'downtime defines runtime-slice completeness helpers'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
