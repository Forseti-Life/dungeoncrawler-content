/**
 * @file
 * Contract test: room-scene encounter legality must follow availability contract.
 *
 * Run with:
 *   node tests/room_scene_spell_legality_contract_test.js
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

const source = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterPhaseHandler.php'),
  'utf8',
);

console.log('\n=== Room-scene spell legality contract ===');

assert(
  source.includes('if ($this->isRoomSceneMode($game_state)) {')
    && source.includes('$available_actions = $this->getAvailableActions($game_state, $dungeon_data, $actor_id ?: NULL);')
    && !source.includes("Action '$type' is not legal during room-scene encounter."),
  'room-scene validation relies on authoritative available actions instead of a hardcoded deny list'
);

assert(
  source.includes("Action '$type' is not currently available for this actor."),
  'room-scene legality failures now resolve through standard availability messaging'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
