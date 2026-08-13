/**
 * @file
 * Contract test: room chat only runs interjection generation for engaged NPCs.
 *
 * Run with:
 *   node tests/room_chat_engaged_npc_gate_contract_test.js
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

const harnessSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceChannelAndSessionTrait.php'),
  'utf8'
);

console.log('\n=== Room chat engaged NPC gate contract ===');

assert(
  harnessSource.includes('$max_engaged_responders = 1;')
    && harnessSource.includes('$engaged_npcs = array_slice($engaged_npcs, 0, $max_engaged_responders);'),
  'Turn plan caps engaged NPC responders to one by default'
);

assert(
  harnessSource.includes("'candidate_npc_refs' => $candidate_npc_refs")
    && harnessSource.includes("'engaged_npc_refs' => $speaking_npc_refs")
    && harnessSource.includes("'ordered_npcs' => $engaged_npcs"),
  'Turn plan executes engaged NPC order while preserving candidate-vs-engaged diagnostics'
);

assert(
  harnessSource.includes('if ($speaker_can_interject) {')
    && harnessSource.includes('$built_messages = $this->buildNpcInterjectionMessage('),
  'Harness executes full NPC interjection generation only for engaged refs'
);

assert(
  harnessSource.includes('$directly_addressed_npc = $this->resolveDirectlyAddressedNpc($room_npcs, $player_message, FALSE);')
    && harnessSource.includes('$directly_addressed_npc = $this->selectHighestCharismaNpc($room_npcs);'),
  'Turn plan continues active conversation first, then falls back to highest-charisma NPC when no direct target is resolved'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
