/**
 * @file
 * Contract coverage for room-chat transition-mode plumbing.
 *
 * Run with:
 *   node tests/room_chat_transition_mode_contract_test.js
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

const writeSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChat/RoomChatWriteEndpointOrchestrator.php'),
  'utf8'
);
const runtimeSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmActorRuntimeService.php'),
  'utf8'
);
const dispatchSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChat/RoomChatPostDispatchOrchestrator.php'),
  'utf8'
);
const streamFlowSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChat/RoomChatStreamFlowOrchestrator.php'),
  'utf8'
);
const roomChatCoreFlowSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceCoreFlowTrait.php'),
  'utf8'
);

console.log('\n=== Room chat transition mode contract ===');

assert(
  writeSource.includes("['legacy', 'dual_transition', 'actor_scoped']")
    && writeSource.includes("'response_mode' => $response_mode")
    && writeSource.includes("'include_legacy_overlay' => $include_legacy_overlay")
    && writeSource.includes('Invalid response mode'),
  'Write endpoint normalizes explicit response_mode and include_legacy_overlay flags and rejects invalid mode values'
);
assert(
  dispatchSource.includes("'response_mode' => (string) ($request_context['response_mode'] ?? 'actor_scoped')")
    && dispatchSource.includes("'include_legacy_overlay' => !empty($request_context['include_legacy_overlay'])"),
  'Post dispatch defaults to actor_scoped and forwards explicit transition options'
);
assert(
  streamFlowSource.includes("'response_mode' => (string) ($options['response_mode'] ?? 'actor_scoped')")
    && streamFlowSource.includes("'include_legacy_overlay' => !empty($options['include_legacy_overlay'])"),
  'Stream flow forwards explicit response-mode controls into direct RoomChatService writes'
);
assert(
  runtimeSource.includes('resolveResponseMode')
    && runtimeSource.includes('shouldIncludeLegacyOverlay')
    && runtimeSource.includes("$chat_result['compatibility_overlay'] =")
    && runtimeSource.includes("$chat_result['response_mode'] = $response_mode;")
    && runtimeSource.includes("unset($chat_result['dungeon_data'], $chat_result['debug_trace']);")
    && runtimeSource.includes("return $response_mode === 'legacy';")
    && runtimeSource.includes('Invalid room chat response mode'),
  'GM actor runtime resolves response mode, rejects invalid values, builds explicit compatibility overlay, and strips top-level heavy legacy fields in dual transition mode'
);
assert(
  roomChatCoreFlowSource.includes('array $response_options = []')
    && roomChatCoreFlowSource.includes('return $this->finalizeRoomChatResponsePayload($response_payload, $response_options);'),
  'Core room-chat writer accepts response options and applies transport-mode finalization before returning'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
