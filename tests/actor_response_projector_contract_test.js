/**
 * @file
 * Contract coverage for actor-scoped room-chat response projector seams.
 *
 * Run with:
 *   node tests/actor_response_projector_contract_test.js
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

const projectorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/ActorResponseProjector.php'),
  'utf8'
);
const runtimeSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmActorRuntimeService.php'),
  'utf8'
);

console.log('\n=== Actor response projector contract ===');

assert(
  projectorSource.includes('class ActorResponseProjector')
    && projectorSource.includes("protected const RESPONSE_SCHEMA_VERSION = 'room-chat-actor-response-v1';"),
  'ActorResponseProjector seam exists with actor response schema version'
);
assert(
  projectorSource.includes("'runtime_snapshot' =>")
    && projectorSource.includes("'aggression_summary' =>")
    && projectorSource.includes("'combat_entry_summary' =>")
    && projectorSource.includes("if (is_array($runtime_snapshot['aggression_state'] ?? NULL)) {")
    && projectorSource.includes("if (is_array($runtime_snapshot['disposition_state'] ?? NULL)) {")
    && projectorSource.includes("$resolved_disposition_by_target = is_array($runtime_snapshot['resolved_disposition_by_target'] ?? NULL)")
    && projectorSource.includes("if ($resolved_disposition_by_target !== []) {")
    && projectorSource.includes("if (is_array($runtime_snapshot['relationship_attitudes'] ?? NULL)) {")
    && projectorSource.includes("elseif ($resolved_disposition_by_target !== []) {")
    && projectorSource.includes("$this->projectCompatibilityRelationshipAttitudesFromResolvedDisposition($resolved_disposition_by_target)")
    && projectorSource.includes("if (is_array($runtime_snapshot['stance_state'] ?? NULL)) {")
    && projectorSource.includes("if (!is_array($response['stance_summary'] ?? NULL) && is_array($runtime_snapshot['stance_state']['summary'] ?? NULL)) {")
    && projectorSource.includes("$response['resolved_actor_context'] = $this->buildResolvedActorContextEnvelope($response, $runtime_snapshot);")
    && projectorSource.includes('protected function buildResolvedActorContextEnvelope(array $response, array $runtime_snapshot): ?array')
    && projectorSource.includes("'resolved_disposition_by_target' => $resolved_disposition_by_target,")
    && projectorSource.includes('protected function projectCompatibilityRelationshipAttitudesFromResolvedDisposition(array $resolved_disposition_by_target): array')
    && projectorSource.includes("'available_actions' =>")
    && projectorSource.includes("'action_contract' =>")
    && projectorSource.includes("'action_option_families' =>"),
  'ActorResponseProjector projects actor-scoped runtime/legality and derives compatibility relationship_attitudes from resolver DTOs when needed'
);
assert(
  !projectorSource.includes("'dungeon_data' =>"),
  'ActorResponseProjector excludes full dungeon_data from default actor response projection'
);
assert(
  runtimeSource.includes('$this->actorTurnContextLoader->load(')
    && runtimeSource.includes('$this->actorResponseProjector->project(')
    && runtimeSource.includes("$chat_result['actor_response'] = $actor_response;"),
  'GM actor runtime wires actor turn context loader and actor response projector in parallel mode'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
