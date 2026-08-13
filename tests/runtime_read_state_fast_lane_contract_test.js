/**
 * @file
 * Contract test: runtime read state respects canary fast-lane sync bypass.
 *
 * Run with:
 *   node tests/runtime_read_state_fast_lane_contract_test.js
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

const coordinatorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GameCoordinatorService.php'),
  'utf8',
);

console.log('\n=== Runtime read-state fast-lane contract ===');

assert(
  coordinatorSource.includes('public function getRuntimeReadState(int $campaign_id, ?string $actor_id = NULL): array')
    && coordinatorSource.includes('$bypass_active_room_sync = $this->shouldBypassActionAvailabilityActiveRoomSync($campaign_id);')
    && coordinatorSource.includes("'trace_id' => 'runtime_read_state'")
    && coordinatorSource.includes('$context = $this->resolveActionAvailabilityContext(')
    && coordinatorSource.includes('!$bypass_active_room_sync'),
  'Runtime read state resolves context through the same campaign-scoped sync-bypass gate as action availability'
);

assert(
  coordinatorSource.includes("'bypass_active_room_sync' => $bypass_active_room_sync,")
    && coordinatorSource.includes("'membership_projection_mode' => $this->shouldEnableActionAvailabilityMembershipProjection($campaign_id),"),
  'Runtime read state propagates rollout diagnostics into context resolution'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
