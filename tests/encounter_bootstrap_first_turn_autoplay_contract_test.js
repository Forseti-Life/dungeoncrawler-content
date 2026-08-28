/**
 * @file
 * Contract test: encounter bootstrap must drive the first turn through the
 * actor harness for ANY non-player actor, not just enemies.
 *
 * Regression: when an ally/familiar won initiative, the bootstrap kickoff gate
 * tested `$first_team === 'enemy'` and therefore never ran the harness. The
 * encounter deadlocked on round 1 waiting for input no human would supply,
 * while the turn-advance path in processEndTurn() correctly gated on
 * `!== 'player'`. The two gates must agree.
 *
 * Run with:
 *   node tests/encounter_bootstrap_first_turn_autoplay_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const bootstrapSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterNavigationTransitionCoordinatorTrait.php'),
  'utf8'
);
const advanceSource = fs.readFileSync(
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

console.log('\n=== Encounter bootstrap first-turn autoplay contract ===');

assert(
  bootstrapSource.includes("if ($first_entity && $first_team !== 'player') {"),
  'bootstrap kickoff should drive every non-player first actor, not only enemies'
);

assert(
  !bootstrapSource.includes("if ($first_entity && $first_team === 'enemy') {"),
  'bootstrap kickoff must not restrict first-turn autoplay to the enemy team'
);

assert(
  bootstrapSource.includes("$should_autoplay_in_room_scene = $this->isRoomSceneMode($game_state) && $first_team === 'enemy';")
    && bootstrapSource.includes('? $this->autoPlayNpcTurn($encounter_id, (string) $first_entity, $game_state, $dungeon_data, $campaign_id)')
    && bootstrapSource.includes(': $this->passRoomActorTurn((string) $first_entity, $game_state, $dungeon_data, $campaign_id);'),
  'bootstrap kickoff should mirror the room-scene autoplay/pass split used on turn advance'
);

assert(
  advanceSource.includes("if ($next_team !== 'player') {")
    && advanceSource.includes("$should_autoplay_in_room_scene = $this->isRoomSceneMode($game_state) && $normalized_next_team === 'enemy';"),
  'turn-advance gate should remain the reference behaviour the bootstrap gate mirrors'
);

assert(
  bootstrapSource.includes('$initial_advance = $this->processEndTurn($encounter_id, (string) $first_entity, $game_state, $dungeon_data, $campaign_id);'),
  'bootstrap kickoff should advance the turn after the first actor acts so the encounter cannot stall'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
