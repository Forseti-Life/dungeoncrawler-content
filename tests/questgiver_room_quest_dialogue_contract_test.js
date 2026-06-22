/**
 * Focused regression for room questgiver dialogue surfacing all authored quests.
 *
 * Run with:
 *   node tests/questgiver_room_quest_dialogue_contract_test.js
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

const source = fs.readFileSync(path.resolve(__dirname, '../src/Service/RoomChatService.php'), 'utf8');

console.log('\n=== Questgiver room quest dialogue contract ===');

assert(
  source.includes('$lines = [];') &&
  source.includes("return '\"' . $speaker . implode(' ', $lines) . '\"';"),
  'Questgiver room quest dialogue aggregates multiple authored quest lines into one response'
);

assert(
  source.includes("if (count($lines) >= 8)"),
  'Questgiver room quest dialogue keeps a bounded authored quest list'
);

assert(
  source.includes('applyDirectQuestgiverDialogueQuestState($campaign_id, $character_id, $entity_ref, $display_name, $room_id, $dungeon_data);'),
  'Direct questgiver room dialogue applies deterministic quest state changes when the NPC surfaces authored quests'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
