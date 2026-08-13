/**
 * @file
 * Contract test: room chat surfaces must forward combat entry projections.
 *
 * Run with:
 *   node tests/room_chat_combat_entry_projection_contract_test.js
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

const coreFlowSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceCoreFlowTrait.php'),
  'utf8',
);
const responseSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceIntentAndDeterminismTrait.php'),
  'utf8',
);
const continuationSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceNpcDialogueAndQuestLeadTrait.php'),
  'utf8',
);
const roomSchema = JSON.parse(
  fs.readFileSync(path.resolve(__dirname, '../config/schemas/room_chat_response.schema.json'), 'utf8'),
);
const queuedSchema = JSON.parse(
  fs.readFileSync(path.resolve(__dirname, '../config/schemas/queued_room_continuation.schema.json'), 'utf8'),
);

console.log('\n=== Room chat combat-entry projection contract ===');

assert(
  coreFlowSource.includes('$this->applyCombatInitiationProjectionToRoomResult($result, $gm_result[\'canonical_actions\']);')
    && coreFlowSource.includes('protected function applyCombatInitiationProjectionToRoomResult(array &$result, array $canonical_actions): void')
    && coreFlowSource.includes("if (is_array($combat_action_result['aggression_summary'] ?? NULL)) {"),
  'Room chat core flow routes combat entry projection extraction through canonical helper'
);

assert(
  responseSource.includes("'aggression_summary',")
    && responseSource.includes("'combat_entry_summary',"),
  'Room chat response envelope allows aggression/combat entry summaries'
);

assert(
  continuationSource.includes("'aggression_summary',")
    && continuationSource.includes("'combat_entry_summary',"),
  'Queued continuation envelope allows aggression/combat entry summaries'
);

assert(
  roomSchema.properties?.aggression_summary
    && roomSchema.properties?.combat_entry_summary
    && queuedSchema.properties?.aggression_summary
    && queuedSchema.properties?.combat_entry_summary,
  'Room chat and queued continuation schemas include aggression/combat entry summary fields'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
