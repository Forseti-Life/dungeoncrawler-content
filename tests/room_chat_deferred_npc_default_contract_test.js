/**
 * @file
 * Contract test: room-chat defaults to inline NPC interjections.
 *
 * Run with:
 *   node tests/room_chat_deferred_npc_default_contract_test.js
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

const endpointSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChat/RoomChatWriteEndpointOrchestrator.php'),
  'utf8',
);
const transportSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmActorChatTransportService.php'),
  'utf8',
);
const subsystemSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GameMasterSubsystemService.php'),
  'utf8',
);
const coreFlowSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceCoreFlowTrait.php'),
  'utf8',
);

console.log('\n=== Inline NPC interjection default contract ===');

assert(
  endpointSource.includes('bool $defer_npc_interjections = FALSE,')
    && endpointSource.includes(
      "return $this->postPlayerRoomChatViaEncounterTalk("
    )
    && endpointSource.includes(",\n        FALSE,\n        $suppress_gm,\n        $options"),
  'Write endpoint routes standard player room chat with inline NPC interjections enabled'
);

assert(
  transportSource.includes('$defer_npc_interjections = FALSE;')
    && transportSource.includes("$this->roomChatService->postMessage("),
  'GM actor chat transport uses inline NPC interjections by default for validated player room chat'
);

assert(
  subsystemSource.includes('bool $defer_npc_interjections = FALSE,')
    && subsystemSource.includes("'defer_npc_interjections' => $defer_npc_interjections,"),
  'GM subsystem route envelope preserves inline NPC interjection defaults for free room chat'
);

assert(
  coreFlowSource.includes('$can_evaluate_room_interjections = (')
    && coreFlowSource.includes("&& $type === 'player'")
    && coreFlowSource.includes('$interjection_gm_narrative = $gm_result !== NULL')
    && coreFlowSource.includes(': $message;'),
  'Core flow evaluates room NPC interjections on player turns even when deterministic lane omits GM payload'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
