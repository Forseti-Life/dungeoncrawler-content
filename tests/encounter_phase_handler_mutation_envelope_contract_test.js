/**
 * @file
 * Contract test: encounter phase handler mutation-envelope completeness.
 *
 * Run with:
 *   node tests/encounter_phase_handler_mutation_envelope_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const source = require('./helpers/php-source.js').readEncounterPhaseHandlerSource();

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

console.log('\n=== Encounter phase handler mutation-envelope contract ===');

assert(
  source.includes('$pre_slice_fingerprints = $this->computeRuntimeSliceFingerprints($dungeon_data);'),
  'processIntent captures pre-mutation runtime-slice fingerprints'
);
assert(
  source.includes('$result[\'mutation_envelope\'] = $this->ensureMutationEnvelopeIncludesChangedSlices('),
  'processIntent enforces changed-slice coverage on mutation envelopes'
);
assert(
  source.includes('$dungeon_data,\n      is_array($result[\'mutations\'] ?? NULL) ? $result[\'mutations\'] : []'),
  'processIntent passes mutation descriptors into changed-slice envelope completion'
);
assert(
  source.includes('$result[\'mutations\'] = $this->normalizeMutationDescriptors(')
  && source.includes('protected function normalizeMutationDescriptors(array $mutations, ?string $default_actor_id, string $default_room_id): array'),
  'encounter processIntent normalizes mutation descriptors with default actor/room targets'
);
assert(
  source.includes('protected function ensureMutationEnvelopeIncludesChangedSlices('),
  'encounter handler provides changed-slice envelope completion helper'
);
assert(
  source.includes('$targets = $this->extractMutationEnvelopeTargets($mutations);')
  && source.includes('actor_entities changed without entity mutation targets.')
  && source.includes('rooms changed without room mutation targets.')
  && source.includes('connections changed without connection/room mutation targets.'),
  'encounter changed-slice completion requires descriptor targets per runtime slice'
);
assert(
  source.includes('protected function computeRuntimeSliceFingerprints(array $dungeon_data): array'),
  'encounter handler defines runtime-slice fingerprint helper'
);
assert(
  source.includes("'type' => 'room_encounter_triggered'")
  && source.includes("'field' => 'room.encounter_triggered'"),
  'onEnter includes explicit room mutation descriptor for encounter-trigger writes'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
