/**
 * @file
 * Contract test: room-entry combat trigger must be gated by hostile disposition.
 *
 * Run with:
 *   node tests/room_entry_combat_trigger_policy_contract_test.js
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

console.log('\n=== Room entry combat trigger policy contract ===');

assert(
  source.includes('protected function buildCombatEncounterContext(string $room_id, array $dungeon_data, array $game_state, int $campaign_id): array')
    && source.includes('if (!$this->hasHostileDispositionInRoom($room_id, $dungeon_data, $campaign_id)) {'),
  'Room-entry combat trigger is gated by hostile disposition check'
);

assert(
  source.includes('protected function hasHostileDispositionInRoom(string $room_id, array $dungeon_data, int $campaign_id): bool')
    && source.includes('$disposition_resolver = $this->resolveDispositionResolverService();')
    && source.includes('$resolved = $disposition_resolver->resolveDispositionMap($campaign_id, $source_ref, $targets, [')
    && source.includes("$hostile_flag = (bool) ($dto['policy_flags']['hostile'] ?? FALSE);")
    && source.includes('if ($hostile_flag || $this->isHostileDispositionScore($effective_score)) {')
    && source.includes('$relationship_attitude->resolveEdgeDispositionDetails($source_ref, $target_ref, $campaign_id)')
    && source.includes('protected function isHostileDispositionScore(int $score): bool'),
  'Hostility check uses resolver and score-thresholded canonical authorities'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
