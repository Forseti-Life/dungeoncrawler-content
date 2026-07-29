/**
 * @file
 * Contract test: canonical mutation descriptor schema enforcement.
 *
 * Run with:
 *   node tests/mutation_descriptor_schema_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const files = [
  'src/Service/EncounterPhaseHandler.php',
  'src/Service/ExplorationPhaseHandler.php',
  'src/Service/DowntimePhaseHandler.php',
];

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

function between(source, startNeedle, endNeedle) {
  const start = source.indexOf(startNeedle);
  if (start < 0) return '';
  const end = source.indexOf(endNeedle, start);
  if (end < 0) return source.slice(start);
  return source.slice(start, end);
}

console.log('\n=== Mutation descriptor schema contract ===');

for (const rel of files) {
  const source = fs.readFileSync(path.resolve(__dirname, '..', rel), 'utf8');
  const label = rel.split('/').pop();

  const extractBody = between(
    source,
    'protected function extractMutationEnvelopeTargets(array $mutations): array {',
    'protected function selectMutationTargetedActorEntities('
  );
  assert(
    extractBody.includes("$mutation['entity_id'] ?? NULL")
      && extractBody.includes("$mutation['room_id'] ?? NULL")
      && extractBody.includes("$mutation['connection_id'] ?? NULL"),
    `${label} extracts envelope targets from canonical keys only`
  );
  assert(
    !extractBody.includes("$mutation['entity'] ?? NULL")
      && !extractBody.includes("$mutation['actor'] ?? NULL")
      && !extractBody.includes("$mutation['target_id'] ?? NULL")
      && !extractBody.includes("$mutation['target_room_id'] ?? NULL")
      && !extractBody.includes("$mutation['connector_id'] ?? NULL"),
    `${label} extract-targets path has no legacy alias dependencies`
  );

  const normalizeBody = between(
    source,
    'protected function normalizeMutationDescriptors(array $mutations, ?string $default_actor_id, string $default_room_id): array {',
    '\n  /**\n   * {@inheritdoc}\n   */\n  public function onEnter('
  );
  assert(
    normalizeBody.includes("$mutation['field'] = $mutation['path'];")
      && normalizeBody.includes("$mutation['entity_id'] = $normalized_actor_id;")
      && normalizeBody.includes("$mutation['room_id'] = $normalized_room_id;"),
    `${label} normalizes defaults using canonical keys only`
  );
  assert(
    !normalizeBody.includes("$mutation['entity'] ?? NULL")
      && !normalizeBody.includes("$mutation['actor'] ?? NULL")
      && !normalizeBody.includes("$mutation['target_id'] ?? NULL")
      && !normalizeBody.includes("$mutation['target_room_id'] ?? NULL")
      && !normalizeBody.includes("$mutation['connector_id'] ?? NULL")
      && !normalizeBody.includes("$mutation['actor_id'] = $normalized_actor_id;"),
    `${label} normalize-descriptors path has no legacy alias mapping branches`
  );
}

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
