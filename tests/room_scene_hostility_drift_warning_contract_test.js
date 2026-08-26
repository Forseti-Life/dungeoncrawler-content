/**
 * @file
 * Contract test: room-scene hostility drift emits structured warning telemetry.
 *
 * Run with:
 *   node tests/room_scene_hostility_drift_warning_contract_test.js
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
  path.resolve(__dirname, '../src/Service/GameCoordinatorService.php'),
  'utf8'
);

console.log('\n=== Room-scene hostility drift warning contract ===');

assert(
  source.includes('emitRoomSceneHostilityDivergenceWarning(')
    && source.includes("$mode !== 'room_scene'")
    && source.includes('collectDispositionChangesFromActionResult('),
  'Coordinator emits a dedicated room-scene hostility divergence warning only when mode remains room_scene'
);

assert(
  source.includes("'campaign_id' => $campaign_id")
    && source.includes("'room_id' => (string)")
    && source.includes("'encounter_id' => (int)")
    && source.includes("'mode' => $mode")
    && source.includes("'source_actor_ref' => (string)")
    && source.includes("'target_actor_ref' => (string)")
    && source.includes("'triggering_action_type' => (string) ($intent['type'] ?? '')")
    && source.includes("'source_event_type' => (string) ($trigger['event_type'] ?? '')")
    && source.includes("'event_cursor' => (int) ($game_state['event_log_cursor'] ?? 0)")
    && source.includes("'recent_event_ids' => $event_ids"),
  'Warning payload includes required campaign, room, encounter, trigger, and event cursor/id diagnostics'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
