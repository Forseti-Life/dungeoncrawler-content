/**
 * @file
 * Contract test: room-entry warmup split queues deferred work off the visible path.
 *
 * Run with:
 *   node tests/room_entry_warmup_split_contract_test.js
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

const explorationSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ExplorationPhaseHandler.php'),
  'utf8',
);
const coordinatorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GameCoordinatorService.php'),
  'utf8',
);

console.log('\n=== Room-entry warmup split contract ===');

assert(
  explorationSource.includes('protected function shouldSplitRoomEntryWarmup(int $campaign_id): bool')
    && explorationSource.includes("getenv('DC_ROOM_ENTRY_WARMUP_SPLIT_ENABLED')")
    && explorationSource.includes("get('room_entry_warmup_split_enabled')")
    && explorationSource.includes("get('latency_toggle_canary_campaign_ids')")
    && explorationSource.includes('protected function enqueueRoomEntryWarmupTasks(array &$game_state, int $campaign_id, string $room_id, array $room_entities): array')
    && explorationSource.includes("'mode' => 'deferred'")
    && explorationSource.includes("'room_entry_warmup' => $room_entry_warmup"),
  'Exploration room transition supports deferred warmup mode with queue metadata'
);

assert(
  explorationSource.includes('ensure_room_npc_psychology_profiles')
    && explorationSource.includes('refresh_room_actor_projection')
    && explorationSource.includes('refresh_institution_membership_projection')
    && explorationSource.includes('prebuild_actor_action_availability_for_room'),
  'Exploration warmup queue includes canonical room-entry deferred task set'
);

assert(
  coordinatorSource.includes('protected function shouldSplitRoomEntryWarmup(int $campaign_id): bool')
    && coordinatorSource.includes('protected function enqueueRoomEntryWarmupTasks(int $campaign_id, array $dungeon_data, array &$game_state): bool')
    && coordinatorSource.includes("'room_entry_warmup' => is_array($game_state['room_entry_warmup'] ?? NULL)")
    && coordinatorSource.includes('elseif (!$had_game_state || $warmup_state_changed) {'),
  'Coordinator startup full-state flow persists and returns room-entry warmup state'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
