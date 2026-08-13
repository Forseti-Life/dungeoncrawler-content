/**
 * @file
 * Contract coverage for RoomChatService coreflow response-mode gating.
 *
 * Run with:
 *   node tests/room_chat_coreflow_response_mode_gating_contract_test.js
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

const traitSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceIntentAndDeterminismTrait.php'),
  'utf8'
);
const coreflowSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceCoreFlowTrait.php'),
  'utf8'
);

console.log('\n=== Room chat coreflow response-mode gating contract ===');

assert(
  traitSource.includes("protected function finalizeRoomChatResponsePayload(array $result, array $response_options = []): array {")
    && traitSource.includes("protected function resolveRoomChatResponseTransportMode(array $response_options = []): array {")
    && traitSource.includes("$candidate = 'actor_scoped';")
    && traitSource.includes('Invalid room chat response mode'),
  'Coreflow response finalizer defaults to actor_scoped mode and rejects invalid response modes'
);
assert(
  traitSource.includes("'emit_legacy_payload' => $response_mode === 'legacy',")
    && traitSource.includes("if (!$transport_mode['emit_legacy_payload']) {")
    && traitSource.includes("unset($result['dungeon_data'], $result['debug_trace']);"),
  'Coreflow response finalizer keeps legacy payload only in legacy mode and trims heavy fields otherwise'
);
assert(
  coreflowSource.includes("'dungeon_data' => $transport_mode['emit_legacy_payload'] ? $dungeon_data : [],")
    && coreflowSource.includes("if ($transport_mode['emit_legacy_payload'] && $debug_trace !== NULL && $this->shouldExposeDebugTrace()) {"),
  'Coreflow avoids materializing legacy dungeon/debug payload fields unless compatibility transport is active'
);
assert(
  traitSource.includes("$validation = $this->stateValidationService->validateRoomChatResponse($result);")
    && traitSource.includes('Room chat response contract violation'),
  'Coreflow validates the final emitted response payload after mode-specific shaping'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
