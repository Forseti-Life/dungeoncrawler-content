/**
 * @file
 * Contract coverage for actor-scoped turn context loader seams.
 *
 * Run with:
 *   node tests/actor_turn_context_loader_contract_test.js
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
  path.resolve(__dirname, '../src/Service/GmSubsystem/ActorTurnContextLoader.php'),
  'utf8'
);

console.log('\n=== Actor turn context loader contract ===');

assert(
  source.includes('class ActorTurnContextLoader'),
  'ActorTurnContextLoader seam exists'
);
assert(
  source.includes("'runtime_snapshot' =>")
    && source.includes("'visible_entities' =>")
    && source.includes("'visible_npcs' =>")
    && source.includes("'connected_rooms' =>")
    && source.includes("'hostile_targets' =>"),
  'Actor turn context loader projects actor-scoped runtime snapshot surfaces'
);
assert(
  source.includes("'legal_actions' =>")
    && source.includes("'available_actions' =>")
    && source.includes("'action_contract' =>")
    && source.includes("'action_option_families' =>"),
  'Actor turn context loader includes legal action context'
);
assert(
  !source.includes("'dungeon_data' =>")
    && !source.includes('dungeon_data transport'),
  'Actor turn context loader does not reintroduce full dungeon_data payload transport'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

