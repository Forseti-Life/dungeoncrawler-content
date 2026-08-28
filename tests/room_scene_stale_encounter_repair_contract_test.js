/**
 * @file
 * Contract test: stale room-scene encounters must be upgraded to hostile
 * combat before NPC room-scene pass logic executes.
 *
 * Run with:
 *   node tests/room_scene_stale_encounter_repair_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const source = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterPhaseHandlerRouteExecutionCorePartCTrait.php'),
  'utf8'
);

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

console.log('\n=== Room-scene stale encounter repair contract ===');

assert(
  source.includes("if ($next_team !== 'player' && $this->isRoomSceneMode($game_state))")
    && source.includes('resolveBootstrapEncounterInitialization($room_id, $game_state, $dungeon_data, $campaign_id, (string) $next_entity)')
    && source.includes("$bootstrap_context['combat_context']['should_trigger']"),
  'end-turn processing re-evaluates stale room-scene encounters through the shared bootstrap hostility seam before NPC pass logic'
);

assert(
  source.includes('$exit_result = $this->onExit($game_state, $dungeon_data, $campaign_id);')
    && source.includes("is_array($exit_result['events'] ?? NULL) ? $exit_result['events'] : []")
    && source.includes('$enter_result = $this->onEnter($bootstrap_context[\'combat_context\'], $game_state, $dungeon_data, $campaign_id);')
    && source.includes("is_array($enter_result['events'] ?? NULL) ? $enter_result['events'] : []")
    && source.includes('resolveInitiativeParticipantTeam($current_turn_entity, $game_state)'),
  'stale room-scene repair cleanly migrates the running encounter into hostile combat, unwraps lifecycle events safely, and returns the new canonical turn owner'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
