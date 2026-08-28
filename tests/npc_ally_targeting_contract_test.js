/**
 * @file
 * Contract test: allied/follower NPC autoplay must target hostile opponents,
 * not blindly the nearest player.
 *
 * Run with:
 *   node tests/npc_ally_targeting_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const supportSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterPhaseHandlerRouteExecutionSupportTrait.php'),
  'utf8'
);
const coordinatorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterNpcTurnCoordinatorTrait.php'),
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

console.log('\n=== NPC ally targeting contract ===');

assert(
  supportSource.includes('$nearest_target = $this->findNearestAliveOpponent($entity_id, $game_state);')
    && supportSource.includes('$has_adjacent_target = $this->hasAdjacentAliveOpponent($npc, $game_state);')
    && supportSource.includes("'nearest_target' => $nearest_target,"),
  'NPC tactical contracts should derive targeting from nearest hostile opponents and record that basis'
);

assert(
  supportSource.includes('$team = $this->normalizeCombatTeam((string) ($npc[\'team\'] ?? \'\'));')
    && supportSource.includes("if (in_array($team, ['player', 'ally'], TRUE)) {")
    && supportSource.includes("return ['enemy'];")
    && supportSource.includes("if ($team === 'enemy') {")
    && supportSource.includes("return ['player', 'ally'];"),
  'opposition-team resolver should send allies toward enemies and enemies toward player/ally threats'
);

assert(
  supportSource.includes("if (in_array($value, ['ally', 'friendly', 'companion'], TRUE)) {")
    && supportSource.includes("if (in_array($value, ['enemy', 'hostile', 'monster', 'monsters', 'npc', 'creature'], TRUE)) {")
    && supportSource.includes("if (in_array($value, ['player', 'player_character', 'pc', 'party', 'adventurer', 'hero'], TRUE)) {"),
  'team normalization should fold team synonyms onto canonical player/ally/enemy values before opposition lookup'
);

assert(
  supportSource.includes('$nearest = $this->findNearestAliveOpponent($entity_id, $game_state);')
    && supportSource.includes('fn(string $nearest_actor_id, array $nearest_state): ?string => $this->findNearestAliveOpponent($nearest_actor_id, $nearest_state)')
    && coordinatorSource.includes('fn(string $actor_id, array $state): ?string => $this->findNearestAliveOpponent($actor_id, $state),'),
  'NPC autoplay planning and fallback targeting should use nearest hostile opponents instead of nearest players'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
