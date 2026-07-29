/**
 * @file
 * Contract test: coordinator runtime-state persistence hardening.
 *
 * Run with:
 *   node tests/game_coordinator_runtime_state_persistence_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const source = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GameCoordinatorService.php'),
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

console.log('\n=== Coordinator runtime-state persistence contract ===');

assert(
  source.includes("public function transitionPhase(int $campaign_id, string $target_phase, array $context = []): array")
  && source.includes("ensurePersistedRuntimeStateMatches($campaign_id, $game_state, (string) ($dungeon_data['active_room_id'] ?? ''));"),
  'transitionPhase verifies persisted runtime state after minimal-write persistence'
);
assert(
  source.includes('public function startCombatEncounter(int $campaign_id, array $context = []): array')
  && source.includes("ensurePersistedRuntimeStateMatches($campaign_id, $game_state, (string) ($dungeon_data['active_room_id'] ?? ''));"),
  'startCombatEncounter verifies persisted runtime state after minimal-write persistence'
);
assert(
  source.includes('Mutation envelope contract violation: failed to persist campaign_state for campaign %d.'),
  'persistMutationEnvelope hard-fails when campaign-state slice persistence fails'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
